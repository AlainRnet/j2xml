<?php
/**
 * @package		J2XML
 * @subpackage	lib_j2xml
 *
 * @author		Helios Ciancio <info@eshiol.it>
 * @link		https://www.eshiol.it
 * @copyright	Copyright (C) 2010 - 2019 Helios Ciancio. All Rights Reserved
 * @license		http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL v3
 * J2XML is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

namespace eshiol\J2XML\Table;

defined('JPATH_PLATFORM') or die;

use eshiol\J2XML\Table\Category;
use eshiol\J2XML\Table\Table;
use Joomla\Component\Fields\Administrator\Table\FieldTable;

//use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;

\JLoader::import('eshiol.j2xml.Table.Category');
\JLoader::import('eshiol.j2xml.Table.Table');

/**
 * Field table
 *
 * @since	17.6.299
 */
class Field extends Table
{
	/**
	 * Constructor
	 *
	 * @param   \JDatabaseDriver  $db  A database connector object
	 *
	 * @since 17.6.299
	 */
	public function __construct(\JDatabaseDriver $db)
	{
		\JLog::add(new \JLogEntry(__METHOD__, \JLog::DEBUG, 'lib_j2xml'));

		parent::__construct('#__fields', 'id', $db);
	}

	/**
	 * 
	 * {@inheritDoc}
	 * @see Table::toXML()
	 */
	function toXML($mapKeysToText = false)
	{
		\JLog::add(new \JLogEntry(__METHOD__, \JLog::DEBUG, 'lib_j2xml'));

		$this->_excluded			= array_merge($this->_excluded, array('group_id'));

		// $this->_aliases['category'] = 'SELECT c.path FROM #__categories c, #__fields_categories fc WHERE c.id = fc.category_id AND fc.field_id ='.(int)$this->id;  
		$this->_aliases['category']	= (string) $this->_db->getQuery(true)
			->select($this->_db->qn('c.path'))
			->from($this->_db->qn('#__categories', 'c'))
			->from($this->_db->qn('#__fields_categories', 'fc'))
			->where($this->_db->qn('c.id') . ' = ' . $this->_db->qn('fc.category_id'))
			->where($this->_db->qn('fc.field_id') . ' = ' . (int) $this->id);
		\JLog::add(new \JLogEntry($this->_aliases['category'], \JLog::DEBUG, 'lib_j2xml'));

		return parent::_serialize();
	}

	/**
	 * Import data
	 *
	 * @param	\SimpleXMLElement	$xml	xml
	 * @param	Registry	$params
	 *     @option	int	'fields'	0: No | 1: Yes, if not exists | 2: Yes, overwrite if exists
	 *     @option  string 'context'
	 *
	 * @throws
	 * @return	void
	 * @access	public
	 *
	 * @since	18.8.310
	 */
	public static function import($xml, $params)
	{
		\JLog::add(new \JLogEntry(__METHOD__, \JLog::DEBUG, 'lib_j2xml'));

		$import_fields = $params->get('fields', 0);
		if ($import_fields == 0) return;

		$context	 = $params->get('context');
		$db			 = \JFactory::getDBO();
		$nullDate	 = $db->getNullDate();
		$userid		 = \JFactory::getUser()->id;

		foreach($xml->xpath("//j2xml/field") as $record)
		{
			self::prepareData($record, $data, $params);

			\JLog::add(new \JLogEntry(__METHOD__, \JLog::DEBUG, 'lib_j2xml'));
			$field = $db->setQuery($db->getQuery(true)
				->select($db->qn('id'))
				->select($db->qn('name'))
				->from($db->qn('#__fields'))
				->where($db->qn('context').' = '.$db->q($data['context']))
				->where($db->qn('name').' = '.$db->q($data['name'])))
				->loadObject();

			if (!$field || ($import_fields == 2))
			{
				/** Joomla! 3.8 compatibility */
				\JLoader::register('FieldTable', JPATH_ADMINISTRATOR . '/components/com_fields/Table/FieldTable.php');
				if (!class_exists('\Joomla\Component\Fields\Administrator\Table\FieldTable'))
				{ // Joomla! 3.8 and beyond
					\JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_fields/tables');
					$table = \JTable::getInstance('Field', 'FieldsTable');
				}
				else 
				{ // Joomla! 4
					$table = new FieldTable($db);
				}

				if (!$field)
				{ // new field
					$data['id'] = null;
				}
				else
				{ // field already exists
					$data['id'] = $field->id;
					$table->load($data['id']);
				}

				// TODO: Trigger the onContentBeforeSave event.
				$table->bind($data);
				\JLog::add(new \JLogEntry(print_r($data, true), \JLog::DEBUG, 'lib_j2xml'));
				if ($table->store())
				{
					\JLog::add(new \JLogEntry(\JText::sprintf('LIB_J2XML_MSG_FIELD_IMPORTED', $table->title), \JLog::INFO, 'lib_j2xml'));
					// TODO: Trigger the onContentAfterSave event.
				}
				else
				{
					\JLog::add(new \JLogEntry(\JText::sprintf('LIB_J2XML_MSG_FIELD_NOT_IMPORTED', $data['title'], $table->getError()), \JLog::ERROR, 'lib_j2xml'));
				}
				$table = null;
			}
		}
	}

	/**
	 *
	 * {@inheritDoc}
	 * @see Table::prepareData()
	 */
	public static function prepareData($record, &$data, $params)
	{
		\JLog::add(new \JLogEntry(__METHOD__, \JLog::DEBUG, 'lib_j2xml'));

		parent::prepareData($record, $data, $params);

		if (!isset($data['description']))
		{
			$data['description'] = '';
		}

		if (isset($data['modified_time']) && ($data['modified_time'] != \JFactory::getDbo()->getNullDate()))
		{
			$data['modified_time'] = self::fixdate($data['modified_time']);
		}
	}

	/**
	 * Export data
	 *
	 * @param	int					$id		the id of the item to be exported
	 * @param	\SimpleXMLElement	$xml	xml
	 * @param	array	$options
	 *
	 * @throws
	 * @return	void
	 * @access	public
	 *
	 * @since	18.8.310
	 */
	public static function export($id, &$xml, $options)
	{
		\JLog::add(new \JLogEntry(__METHOD__, \JLog::DEBUG, 'lib_j2xml'));
		\JLog::add(new \JLogEntry('id: ' . $id, \JLog::DEBUG, 'lib_j2xml'));
		\JLog::add(new \JLogEntry('options: ' . print_r($options, true), \JLog::DEBUG, 'lib_j2xml'));

		if ($xml->xpath("//j2xml/field/id[text() = '" . $id . "']"))
		{
			return;
		}

		$db			= \JFactory::getDbo();
		$item		= new Field($db);
		if (!$item->load($id))
		{
			return;
		}

		$doc		= dom_import_simplexml($xml)->ownerDocument;
		$fragment	= $doc->createDocumentFragment();

		$fragment->appendXML($item->toXML());
		$doc->documentElement->appendChild($fragment);

		if ($options['categories'])
		{
			$query = $db->getQuery(true)
				->select('category_id')
				->from('#__fields_categories')
				->where('field_id = '.$id);
			$db->setQuery($query);

			$ids_category = $db->loadColumn();
			foreach ($ids_category as $id_category)
			{
				Category::export($id_category, $xml, $options);
			}
		}
	}
}
