<?php namespace Pyro\Module\Pages\Model;

use Pyro\Module\Navigation\Model\Link;

/**
 * Pages model
 *
 * @author   PyroCMS Dev Team
 * @package  PyroCMS\Core\Modules\Pages\Models
 * @link     http://docs.pyrocms.com/2.3/api/classes/Pyro.Module.Pages.Model.Page.html
 */
class Page extends \Illuminate\Database\Eloquent\Model
{
    /**
     * Define the table name
     *
     * @var string
     */
    protected $table = 'pages';

    /**
     * Disable updated_at and created_at on table
     *
     * @var boolean
     */
    public $timestamps = false;

	/**
	 * Array containing the validation rules
	 *
	 * @var array
	 */
	public static $validate = array(
		array(
			'field' => 'title',
			'label'	=> 'lang:global:title',
			'rules'	=> 'trim|required|max_length[250]'
		),
		'slug' => array(
			'field' => 'slug',
			'label'	=> 'lang:global:slug',
			'rules'	=> 'trim|required|alpha_dot_dash|max_length[250]|callback__check_slug'
		),
		array(
			'field'	=> 'css',
			'label'	=> 'lang:pages:css_label',
			'rules'	=> 'trim'
		),
		array(
			'field'	=> 'js',
			'label'	=> 'lang:pages:js_label',
			'rules'	=> 'trim'
		),
		array(
			'field' => 'meta_title',
			'label' => 'lang:pages:meta_title_label',
			'rules' => 'trim|max_length[250]'
		),
		array(
			'field'	=> 'meta_keywords',
			'label' => 'lang:pages:meta_keywords_label',
			'rules' => 'trim|max_length[250]'
		),
		array(
			'field'	=> 'meta_description',
			'label'	=> 'lang:pages:meta_description_label',
			'rules'	=> 'trim'
		),
		array(
			'field' => 'restricted_to[]',
			'label'	=> 'lang:pages:access_label',
			'rules'	=> 'trim|required'
		),
		array(
			'field' => 'rss_enabled',
			'label'	=> 'lang:pages:rss_enabled_label',
			'rules'	=> 'trim'
		),
		array(
			'field' => 'comments_enabled',
			'label'	=> 'lang:pages:comments_enabled_label',
			'rules'	=> 'trim'
		),
		array(
			'field' => 'is_home',
			'label'	=> 'lang:pages:is_home_label',
			'rules'	=> 'trim'
		),
		array(
			'field' => 'strict_uri',
			'label'	=> 'lang:pages:strict_uri_label',
			'rules'	=> 'trim'
		),
		array(
			'field'	=> 'status',
			'label'	=> 'lang:pages:status_label',
			'rules'	=> 'trim|alpha|required'
		),
		array(
			'field' => 'navigation_group_id[]',
			'label' => 'lang:pages:navigation_label',
			'rules' => 'numeric'
		)
	);

	// For streams
	public static $compiled_validate = array();

	/**
	 * Relationship: Type
	 *
	 * @return Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function type()
    {
        return $this->belongsTo('Pyro\Module\Pages\Model\PageType');
    }

	/**
	 * Relationship: Parent
	 *
	 * @return Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function parent()
    {
        return $this->belongsTo('Pyro\Module\Pages\Model\Page', 'parent_id');
    }

	/**
	 * Relationship: Children
	 *
	 * @return Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function children()
    {
        return $this->hasMany('Pyro\Module\Pages\Model\Page', 'parent_id');
    }

	/**
	 * Find page by id and status
	 *
	 * @param string $status Live or draft?
	 *
	 * @return Page
	 */
	public static function findStatus($status)
	{
		return static::where('status', '=', $status)->all();
	}

	/**
	 * Find page by id and status
	 *
	 * @param int $id The id of the page.
	 * @param string $status Live or draft?
	 *
	 * @return Page
	 */
	public static function findByIdAndStatus($id, $status)
	{
		return static::where('id', '=', $id)
			->where('status', '=', $status)
			->first();
	}

	/**
	 * Find page by id and status
	 *
	 * @param int $id The id of the page.
	 * @param string $status Live or draft?
	 *
	 * @return Page
	 */
	public static function findManyByParentAndStatus($parent_id, $status)
	{
		return static::where('parent_id', '=', $parent_id)
			->where('status', '=', $status)
			->get();
	}

	/**
	 * Find a page by its URI
	 *
	 * @param string $uri The uri of the page.
	 * @param bool $is_request Is this an http request or called from a plugin
	 *
	 * @return Page
	 */
	public static function findByUri($uri, $is_request = false)
	{
		// it's the home page
		if ($uri === null) {

			$page = static::where('is_home', '=', true)
				->with('type')
				->take(1)
				->first();

		} else {
			// If the URI has been passed as an array, implode to create a string of uri segments
			is_array($uri) && $uri = trim(implode('/', $uri), '/');

			// $uri gets shortened so we save the original
			$original_uri = $uri;
			$page = false;
			$i = 0;

			while ( ! $page and $uri and $i < 15) { /* max of 15 in case it all goes wrong (this shouldn't ever be used) */
				$page = static::where('uri', '=', $uri)
					->with('type')
					->take(1)
					->first();

				// if it's not a normal page load (plugin or etc. that is not cached)
				// then we won't do our recursive search
				if (! $is_request) {
					break;
				}

				// if we didn't find a page with that exact uri AND there's more than one segment
				if ( ! $page and strpos($uri, '/') !== false) {
					// pop the last segment off and we'll try again
					$uri = preg_replace('@^(.+)/(.*?)$@', '$1', $uri);
				}
				// we didn't find a page and there's only one segment; it's going to 404
				elseif (! $page) {
					break;
				}
				++$i;
			}

			if ($page) {
				// so we found a page but if strict uri matching is required and the unmodified
				// uri doesn't match the page we fetched then we pretend it didn't happen
				if ($is_request and (bool) $page->strict_uri and $original_uri !== $uri) {
					return false;
				}

				// things like breadcrumbs need to know the actual uri, not the uri with extra segments
				$page->base_uri = $uri;
			}
		}

		// looks like we have a 404
		if (! $page) {
			return false;
		}

		// ---------------------------------
		// Get Stream Entry
		// ---------------------------------

		if ($page->entry_id and $page->type->stream_id) {
			ci()->load->driver('Streams');

			// Get Streams
			$stream = ci()->streams_m->get_stream($page->type->stream_id);

			if ($stream) {
				if ($entry = ci()->streams->entries->get_entry($page->entry_id, $stream->stream_slug, $stream->stream_namespace)) {
					foreach ($entry as $key => $value) {
						$page->{$key} = $value;
					}
				}
			}
		}

		return $page;
	}

 //    // --------------------------------------------------------------------------

	// /**
	//  * Get a page from the database.
	//  *
	//  * Also retrieves the content fields for that page.
	//  *
	//  * @param int $id The page id.
	//  * @param bool $get_data Whether to retrieve the stream data for this page or not. Defaults to true.
	//  *
	//  * @return array The page data.
	//  */
	// public function get($id, $get_data = true)
	// {
	// 	$page = $this->db
	// 		->select('pages.*, page_types.id as page_type_id, page_types.stream_id, page_types.body')
	// 		->select('page_types.save_as_files, page_types.slug as page_type_slug, page_types.title as page_type_title, page_types.js as page_type_js, page_types.css as page_type_css')
	// 		->join('page_types', 'page_types.id = pages.type_id', 'left')
	// 		->where('pages.id', $id)
	// 		->get($this->_table)
	// 		->row();

	// 	$page->stream_entry_found = false;

	// 	if ($page and $page->type_id and $get_data) {
	// 		// Get our page type files in case we are grabbing
	// 		// the body/html/css from the filesystem.
	// 		$this->page_type_m->get_page_type_files_for_page($page);

	// 		$this->load->driver('Streams');
	// 		$stream = $this->streams_m->get_stream($page->stream_id);

	// 		if ($stream) {
	// 			$params = array(
	// 				'stream' 	=> $stream->stream_slug,
	// 				'namespace' => $stream->stream_namespace,
	// 				'where' 	=> "`id`='".$page->entry_id."'",
	// 				'limit' 	=> 1
	// 			);

	// 			$ret = $this->streams->entries->get_entries($params);

	// 			if (isset($ret['entries'][0])) {
	// 				// For no collisions
	// 				$ret['entries'][0]['entry_id'] = $ret['entries'][0]['id'];
	// 				unset($ret['entries'][0]['id']);

	// 				$page->stream_entry_found = true;

	// 				return (object) array_merge((array) $page, (array) $ret['entries'][0]);
	// 			}

	// 			$this->page_type_m->get_page_type_files_for_page($page);
	// 		}
	// 	}

	// 	return $page;
	// }

	// /**
	//  * Return page data
	//  *
	//  * @return array An array containing data fields for a page
	//  */
	// public function get_data($stream_entry_id, $stream_slug)
	// {
	// 	return $this->streams->entries->get_entry($stream_entry_id, $stream_slug, 'pages');
	// }

	/**
	 * Set the parent > child relations and child order
	 *
	 * @param array $page
	 */
	public function _set_children($page)
	{
		if ($page->children) {
			foreach ($page->children as $i => $child) {
				$child_id = (int) str_replace('page_', '', $child->id);
				$page_id = (int) str_replace('page_', '', $page->id);

				$child->parent_id = $page_id;
				$child->order = $i;
				$child->save();

				//repeat as long as there are children
				if ($child->children) {
					$this->_set_children($child);
				}
			}
		}
	}

	/**
	 * Does the page have children?
	 *
	 * @param int $parent_id The ID of the parent page
	 *
	 * @return bool
	 */
	public function has_children($parent_id)
	{
		return parent::count_by(array('parent_id' => $parent_id)) > 0;
	}

	/**
	 * Get the child IDs
	 *
	 * @param int $id The ID of the page?
	 * @param array $id_array ?
	 *
	 * @return array
	 */
	public function getDescendantIds($id, $id_array = array())
	{
		$id_array[] = $id;

		$children = $this
			->select('id, title')
			->where('parent_id', '=', $id)
			->get();

		if ($children) {
			// Loop through all of the children and run this function again
			foreach ($children as $child) {
				$id_array = $this->getDescendantIds($child->id, $id_array);
			}
		}

		return $id_array;
	}

    // --------------------------------------------------------------------------

	/**
	 * Build a lookup
	 *
	 * @param int $id The id of the page to build the lookup for.
	 *
	 * @return array
	 */
	public function buildLookup($id = null)
	{
		// Either its THIS page, or one we said
		$current_id = $id ?: $this->id;

		$segments = array();
		do {

			// Only want a bit of this data
			$page = static::select('slug', 'parent_id')
				->find($current_id);

			// Save this first one for later
			if ( ! isset($original_page)) {
				$original_page = $page;
			}

			$current_id = $page->parent_id;
			array_unshift($segments, $page->slug);
		} while ($page->parent_id > 0);

		// Save this new uri by joining the array
		$original_page->uri = implode('/', $segments);

		return $original_page->save();
	}

	/**
	 * Reindex child items
	 *
	 * @param int $id The ID of the parent item
	 */
	public function reindex_descendants($id)
	{
		$descendants = $this->getDescendantIds($id);

		array_walk($descendants, array($this, 'buildLookup'));
	}

	/**
	 * Update lookup.
	 *
	 * Updates lookup for entire page tree used to update
	 * page uri after ajax sort.
	 *
	 * @param array $root_pages An array of top level pages
	 */
	public function update_lookup($root_pages)
	{
		// first reset the URI of all root pages
		$this->db
			->where('parent_id', 0)
			->set('uri', 'slug', false)
			->update('pages');

		foreach ($root_pages as $page) {
			$this->reindex_descendants($page);
		}
	}

    // --------------------------------------------------------------------------

	/**
	 * Create a new page
	 *
	 * @param array $input The page data to insert.
	 * @param obj   $stream The stream tied to this page.
	 *
	 * @return bool `true` on success, `false` on failure.
	 */
	public function createOld($input, $stream = false)
	{
		$this->db->trans_start();

		if ( ! empty($input['is_home'])) {
			// Remove other homepages so this one can have the spot
			$this->skip_validation = true;
			$this->update_by('is_home', 1, array('is_home' => 0));
		}

		// validate the data and insert it if it passes
		$id = $this->insert(array(
			'slug'				=> $input['slug'],
			'title'				=> $input['title'],
			'uri'				=> null,
			'parent_id'			=> (int) $input['parent_id'],
			'type_id'			=> (int) $input['type_id'],
			'css'				=> isset($input['css']) ? $input['css'] : null,
			'js'				=> isset($input['js']) ? $input['js'] : null,
			'meta_title'    	=> isset($input['meta_title']) ? $input['meta_title'] : '',
			'meta_keywords' 	=> isset($input['meta_keywords']) ? $this->keywords->process($input['meta_keywords']) : '',
			'meta_description' 	=> isset($input['meta_description']) ? $input['meta_description'] : '',
			'rss_enabled'		=> ! empty($input['rss_enabled']),
			'comments_enabled'	=> ! empty($input['comments_enabled']),
			'status'			=> $input['status'],
			'created_on'		=> now(),
			'restricted_to'		=> isset($input['restricted_to']) ? implode(',', $input['restricted_to']) : '0',
			'strict_uri'		=> ! empty($input['strict_uri']),
			'is_home'			=> ! empty($input['is_home']),
			'order'				=> now()
		));

		// did it pass validation?
		if ( ! $id) return false;

		// We define this for the field type.
		define('PAGE_ID', $id);

		$this->buildLookup($id);

		// Add a Navigation Link
		if (isset($input['navigation_group_id']) and count($input['navigation_group_id']) > 0) {
			if (isset($input['navigation_group_id']) and is_array($input['navigation_group_id'])) {
				foreach ($input['navigation_group_id'] as $group_id) {
					$link = Link::create(array(
						'title'					=> $input['title'],
						'link_type'				=> 'page',
						'page_id'				=> $id,
						'navigation_group_id'	=> $group_id
					));

					if ($link) {
						//@TODO Fix Me Bro https://github.com/pyrocms/pyrocms/pull/2514
						$this->cache->clear('navigation_m');

						Events::trigger('post_navigation_create', $link);
					}
				}
			}
		}

		// Add the stream data.
		if ($stream) {
			$this->load->driver('Streams');

			// Insert the stream using the streams driver.
			if ($entry_id = $this->streams->entries->insert_entry($input, $stream->stream_slug, $stream->stream_namespace)) {
				// Update with our new entry id
				if ( ! $this->db->limit(1)->where('id', $id)->update($this->_table, array('entry_id' => $entry_id))) {
					return false;
				}
			} else {
				// Something went wrong. Abort!
				return false;
			}
		}

		$this->db->trans_complete();

		return ($this->db->trans_status() === false) ? false : $id;
	}

	/**
	 * Set the homepage
	 */
	public function setHomePage()
	{
		$this->where('is_home', '=', 1)
			->update('is_home', 0);

		$this->update('is_home', 1);
	}

	/**
	 * Delete a Page
	 *
	 * @param int $id The ID of the page to delete
	 *
	 * @return array|bool
	 */
	public function deleteOld($id = 0)
	{
		$this->db->trans_start();

		$ids = $this->getDescendantIds($id);

		foreach ($ids as $id) {
			$page = $this->get($id);

			// Get the stream and delete the entry. Yeah this is a lot of queries
			// but hey we're deleting stuff. Get off my back!
			if ($page->stream_id) {
				if ($stream = $this->streams_m->get_stream($page->stream_id)) {
					$this->streams->entries->delete_entry($page->entry_id, $stream->stream_slug, $stream->stream_namespace);
				}
			}
		}

		// Delete the page.
		$this->db->where_in('id', $ids);
		$this->db->delete('pages');

		// Our navigation links should go as well.
		$this->db->where_in('page_id', $ids);
		$this->db->delete('navigation_links');

		$this->db->trans_complete();

		return ($this->db->trans_status() !== false) ? $ids : false;
	}

    // --------------------------------------------------------------------------

	/**
	 * Check Slug for Uniqueness
	 *
	 * Slugs should be unique among sibling pages.
	 *
	 * @param string $slug The slug to check for.
	 * @param int $parent_id The parent_id if any.
	 * @param int $id The id of the page.
	 *
	 * @return bool
	*/
	public function _unique_slug($slug, $parent_id, $id = 0)
	{
		return (bool) static::where('id', '!=', $id)
			->where('slug', '=', $slug)
			->where('parent_id', '=', $parent_id)
			->count() > 0;
	}

    // --------------------------------------------------------------------------

	/**
	 * Callback to check uniqueness of slug + parent
	 *
	 *
	 * @param $slug slug to check
	 * @return bool
	 */
	 public function _check_slug($slug)
	 {
	 	$page_id = ci()->uri->segment(4);

		if ($this->_unique_slug($slug, ci()->input->post('parent_id'), (int) $page_id)) {
			if (ci()->input->post('parent_id') == 0) {
				$parent_folder = lang('pages:root_folder');
				$url = '/'.$slug;
			} else {
				$page_obj = $this->find($page_id);
				$url = '/'.trim(dirname($page_obj->uri),'.').$slug;
				$page_obj = $this->get(ci()->input->post('parent_id'));
				$parent_folder = $page_obj->title;
			}

			ci()->form_validation->set_message('_check_slug', sprintf(lang('pages:page_already_exist_error'),$url, $parent_folder));
			return false;
		}

		return true;
	}
}
