<?php

namespace Parvula\Repositories;

use Iterator;
use Parvula\BaseRepository;
use Parvula\IterableTrait;
use Parvula\ArrayableInterface;
use Parvula\Models\Page;
use Parvula\PageRenderers\PageRendererInterface;

abstract class PageRepository extends BaseRepository implements Iterator, ArrayableInterface
{

	use IterableTrait;

	/**
	 * @var array<Page>
	 */
	protected $data = [];

	/**
	 * @var PageRendererInterface
	 */
	protected $renderer;

	/**
	 * Constructor
	 *
	 * @param ContentParserInterface $contentParser (optional)
	 */
	function __construct(PageRendererInterface $pageRenderer) {
		$this->setRenderer($pageRenderer);
	}

	/**
	 * Fetch all pages
	 * This method will read each pages
	 * If you want an array of Page use `toArray()` method
	 * Exemple: `$pages->all()->toArray();`
	 *
	 * @param string ($path) PageRepository in a specific sub path
	 * @return PageRepository
	 */
	public function all($path = '') {
		$that = clone $this;
		$that->data = [];

		$pagesIndex = $this->index(true, $path);

		foreach ($pagesIndex as $pageUID) {
			if (!isset($that->data[$pageUID])) {
				$page = $this->read($pageUID);
				$that->data[$page->slug] = $page;
			}
		}

		return $that;
	}

	/**
	 * Order pages
	 *
	 * @param integer ($sortType) Sort order
	 * @param string ($sortField) Sorting field
	 * @return PageRepository
	 */
	public function order($sortType = SORT_ASC, $sortField = 'slug') {
		$that = clone $this;

		if (!is_integer($sortType)) {
			$sortType = SORT_ASC;
		}

		if (!empty($this->data)) {
			$this->arraySortByField($that->data, $sortField, $sortType);
		}

		return $that;
	}

	/**
	 * Sort pages using a user-defined comparison function
	 *
	 * @param  callable $fn
	 * @return PageRepository
	 */
	public function sort(callable $fn) {
		$that = clone $this;

		if (!empty($this->data)) {
			usort($that->data, $fn);
		}

		return $that;
	}

	/**
	 * Show visible pages
	 *
	 * @return PageRepository
	 */
	public function visible() {
		return $this->visibility(true);
	}

	/**
	 * Show hidden pages
	 *
	 * @return PageRepository
	 */
	public function hidden() {
		return $this->visibility(false);
	}

	/**
	 * Filter pages by visibility (hidden or visible)
	 *
	 * @param  boolean $visible
	 * @return PageRepository
	 */
	public function visibility($visible) {
		return $this->filter(function ($page) use ($visible) {
			if ($visible) {
				return !isset($page->hidden) || !$page->hidden || $page->hidden === 'false';
			}
			return isset($page->hidden) && ($page->hidden || $page->hidden !== 'false');
		});
	}

	/**
	 * Show pages without a parent (the 'root' pages)
	 *
	 * @return PageRepository
	 */
	public function withoutParent() {
		return $this->filter(function (Page $page) {
			return (bool) !$page->get('parent');
		});
	}

	/**
	 * Show pages with a parent (the children pages)
	 *
	 * @return PageRepository
	 */
	public function withParent() {
		return $this->filter(function (Page $page) {
			return (bool) $page->get('parent');
		});
	}

	/**
	 * Filter pages
	 *
	 * Example:
	 * ```
	 * // Will just keep pages with a title < 10 characters
	 * $pages->filter(function ($page) {
	 *     return strlen($page->title) < 10;
	 * })
	 * ```
	 *
	 * @param callable $fn
	 * @return PageRepository A clone of current object with filtered pages
	 */
	public function filter(callable $fn) {
		$that = clone $this;
		$that->data = [];

		foreach ($this->data as $page) {
			if ($fn($page) === true) {
				$that->data[] = $page;
			}
		}

		return $that;
	}

	/**
	 * Get all pages to array
	 *
	 * @return array<Page> Return an array of 'Page'
	 */
	public function toArray() {
		if (empty($this->data)) {
			return [];
		}

		$acc = [];
		foreach ($this->data as $page) {
			// TODO is is_array really needed ?
			if (isset($page->children) && !is_array($page->children)) {
				// We have to resolve children to arrays
				$page->children = $page->children->toArray();
			}
			$acc[] = $page;
		}

		return $acc;
	}

	/**
	 * Return pages
	 *
	 * @return array Array of Page
	 */
	public function getPages() {
		return $this->data;
	}

	/**
	 * Sort array of objects from a specific field
	 *
	 * @param array<?> &$arr An array of objects
	 * @param string $field Field name to sort
	 * @param integer $sortType Sorting type (flag)
	 * @return boolean
	 */
	private function arraySortByField(array &$arr, $field, $sortType) {
		$sortFields = [];
		foreach ($arr as $key => $obj) {
			$sortFields[$key] = [];

			if (isset($obj->$field)) {
				$sortFields[$key] = $obj->$field;
			}
		}

		return array_multisort($sortFields, $sortType, $arr);
	}

	/**
	 * Set page renderer
	 *
	 * @param PageRendererInterface $customRenderer
	 */
	public function setRenderer(PageRendererInterface $customRenderer) {
		$this->renderer = $customRenderer;
	}
}