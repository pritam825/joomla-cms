<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_finder
 *
 * @copyright   (C) 2016 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Finder\Administrator\Field;

\defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Component\Finder\Administrator\Helper\LanguageHelper;

/**
 * Supports a select grouped list of finder content map.
 *
 * @since  3.6.0
 */
class ContentmapField extends GroupedlistField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  3.6.0
	 */
	public $type = 'ContentMap';

	/**
	 * Method to get the list of content map options grouped by first level.
	 *
	 * @return  array  The field option objects as a nested array in groups.
	 *
	 * @since   3.6.0
	 */
	protected function getGroups()
	{
		$groups = array();

		// Get the database object and a new query object.
		$db = Factory::getDbo();

		// Levels subquery.
		$levelQuery = $db->getQuery(true);
		$levelQuery->select('title AS branch_title, 1 as level')
			->select($db->quoteName('id'))
			->from($db->quoteName('#__finder_taxonomy'))
			->where($db->quoteName('parent_id') . ' = 1');
		$levelQuery2 = $db->getQuery(true);
		$levelQuery2->select('b.title AS branch_title, 2 as level')
			->select($db->quoteName('a.id'))
			->from($db->quoteName('#__finder_taxonomy', 'a'))
			->join('LEFT', $db->quoteName('#__finder_taxonomy', 'b') . ' ON ' . $db->quoteName('a.parent_id') . ' = ' . $db->quoteName('b.id'))
			->where($db->quoteName('a.parent_id') . ' NOT IN (0, 1)');

		$levelQuery->union($levelQuery2);

		// Main query.
		$query = $db->getQuery(true)
			->select($db->quoteName('a.title', 'text'))
			->select($db->quoteName('a.id', 'value'))
			->select($db->quoteName('d.level'))
			->from($db->quoteName('#__finder_taxonomy', 'a'))
			->join('LEFT', '(' . $levelQuery . ') AS d ON ' . $db->quoteName('d.id') . ' = ' . $db->quoteName('a.id'))
			->where($db->quoteName('a.parent_id') . ' <> 0')
			->order('d.branch_title ASC, d.level ASC, a.title ASC');

		$db->setQuery($query);

		try
		{
			$contentMap = $db->loadObjectList();
		}
		catch (\RuntimeException $e)
		{
			return [];
		}

		// Build the grouped list array.
		if ($contentMap)
		{
			$lang = Factory::getLanguage();
			$name = '';

			foreach ($contentMap as $branch)
			{
				if ((int) $branch->level === 1)
				{
					$name = $branch->text;
				}
				else
				{
					$levelPrefix = str_repeat('- ', max(0, $branch->level - 1));

					if (trim($name, '**') === 'Language')
					{
						$text = LanguageHelper::branchLanguageTitle($branch->text);
					}
					else
					{
						$key = LanguageHelper::branchSingular($branch->text);
						$text = $lang->hasKey($key) ? Text::_($key) : $branch->text;
					}

					// Initialize the group if necessary.
					if (!isset($groups[$name]))
					{
						$groups[$name] = array();
					}

					$groups[$name][] = HTMLHelper::_('select.option', $branch->value, $levelPrefix . $text);
				}
			}
		}

		// Merge any additional groups in the XML definition.
		$groups = array_merge(parent::getGroups(), $groups);

		return $groups;
	}
}
