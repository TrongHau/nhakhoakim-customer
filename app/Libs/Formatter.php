<?php

namespace App\Libs;

trait Formatter
{
	static public $ERROR = 0;
	static public $WARNING = 1;
	static public $NOTICE = 2;
	static public $SUCCESS = 3;
	/*******************
	 * layout variable
	 *******************/
	private $layout;
	private $widgets = [];
	private $message = [];
	private $extraData = [];
	/*********************
	 * pagination variable
	 *********************/
	protected $total = 0;

	/**
	 * items/page
	 * @var int
	 */
	protected $pageItems = 20;

	/**
	 * maximum page
	 * @var int
	 */
	protected $maxPages = 10;

	protected $limit = 20;
	protected $lmstart = 0;

	/**
	 * html content
	 * @var string
	 */
	protected $strContent = '';

	/**
	 * number of page
	 * @var int
	 */
	protected $pages = 0;

	/**
	 * current page
	 * @var int
	 */
	protected $curPage = 0;
	//    const vGrid = "Views", vBool = 'Bool', ERROR = 0, SUCCESS = 1;

	protected function initLayout(): void
	{
		$this->layout = (object)[];
		$this->layout->module = (object)[];
		$this->layout->messages = (object)[];
		$this->layout->application = [
			"name" => 'AI Pacific Vietnam - Hospital Infomation Service',
			"code" => 'invoice',
			"token" => '5sfPL6bMzwfSYrWGRwDbvLaa5TgXFYhN'
		];
		$this->layout->page = [
			"title" => '',
			"author" => '',
			"description" => '',
			"keywords" => ''
		];
		$this->renderWidget();
	}

	private function setLayoutView($data = [])
	{
		if (!empty($this->widgets)) {
			$this->layout->widgets = $this->widgets;
		}
		if (!empty($data)) {
			$this->layout->module->views = $data;
		}
		if (!empty($this->extraData)) {
			$this->layout->data = $this->extraData;
		}
		if (!empty($this->message)) {
			$this->layout->messages = $this->message;
		}
	}
	private function setLayoutPermission($data = [])
	{
		if (!empty($this->widgets)) {
			$this->layout->widgets = $this->widgets;
		}
		if (!empty($data)) {
			$this->layout->module->permissions = $data;
		}
		if (!empty($this->message)) {
			$this->layout->messages = $this->message;
		}
	}
	private function setLayoutBool($flash)
	{
		if (!empty($this->widgets)) {
			$this->layout->widgets = $this->widgets;
		}
		if (is_bool($flash) && isset($flash)) {
			$this->layout->module->code = $flash;
		}
		if (!empty($this->extraData)) {
			$this->layout->data = $this->extraData;
		}
		if (!empty($this->message)) {
			$this->layout->messages = $this->message;
		}
	}

	/**
	 * @param        $data
	 * @param string $type
	 * @param integer $code
	 * @return mixed
	 */
	public function json($data, $type = 'views', $code = 200)
	{
		switch ($type) {
			case 'views':
				$this->setLayoutView($data);
				break;
			case 'bool':
				$this->setLayoutBool($data);
				break;
			case 'permissions':
				$this->setLayoutPermission($data);
				break;
		}
		return response()->json($this->layout, $code);
	}

	private function renderWidget(): void
	{
		$widgets = $this->widgets;
		if (!empty($widgets)) {
			foreach ($widgets as $v) {
				$this->widgets[] = unserialize($v)->run();
			}
		}
	}

	/**
	 * $type = 2: success | 3: warning | others: danger
	 * @param $content
	 * @param null $code
	 * @param int $type
	 */
	protected function addMessage($content, $code = null, $type = 2)
	{
		if (is_string($content)) {
			$this->message[] = $this->formattedMessage($content, $code, $type);
		} else {
			$this->message[] = $content;
		}
	}

	protected function addData($data)
	{
		$this->extraData[] = $data;
	}

	protected function formatValidationMessages($content, $code = null, $type = 1)
	{
		if (is_array($content)) {
			foreach ($content as $key => $item) {
				$message = is_array($item) ? implode(',', $item) : $item;
				$this->message[] = $this->formattedMessage($message, $code, $type, $key);
			}
		} else {
			$this->message[] = $content;
		}
	}

	/**
	 * $type = 2: success | 3: warning | others: danger
	 * @param $mes
	 * @param null $code
	 * @param int $type
	 * @param string $class
	 * @param string $ordering
	 * @return object
	 */
	protected function formattedMessage($mes,  $code = null, $type = 2, $class = '', $ordering = '')
	{
		return (object)[
			"code" => $code,
			"type" => $type,
			"mes" => $mes,
			"contentRaw" => $mes,
			"class" => $class,
			"ordering" => $ordering
		];
	}

	/**
	 * @param        $name
	 * @param        $data
	 * @param string $type
	 *
	 * @return array
	 */
	protected function formatData($name, $data, $type = 'Grid')
	{
		return [
			'type' => $type,
			'name' => $name,
			'data' => $data
		];
	}

	/**
	 * @param        $name
	 * @param        $data
	 * @param string $type
	 *
	 * @return array
	 */
	protected function formatDataPaginationByStore($name, $data, $type = 'Grid')
	{
		$item = null;
		if (isset($data[0]) && !empty($data[0])) {
			$item = json_decode(json_encode($data[0]), true);
		}
		$request = app('request');
		return [
			'type' => $type,
			'name' => $name,
			'data' => $data,
			'pagination' => $this->PaginationtoJson(
				$item['TotalRow'] ?? 0,
				(int) $request->get('limit', 20),
				(int) ($request->get('lmstart', 0) / $request->get('limit', 20)) + 1
			)
		];
	}

	/* 
  * pagination function 
  */
	protected function formatPagination($name, $data, $type = 'Grid')
	{
		if (!is_object($data)) {
			return [
				'type' => $type,
				'name' => $name,
				'data' => $data,
				'pagination' => null
			];
		}
		return [
			'type' => $type,
			'name' => $name,
			'data' => $data->items(),
			'pagination' => $this->PaginationtoJson($data->total(), $data->perPage(), $data->currentPage())
		];
	}

	/**
	 * pagination array function
	 * @param $name
	 * @param $data
	 * @param string $type
	 * @return array
	 */
	protected function formatPaginationArray($name, $data, $type = 'Grid')
	{
		return [
			'type' => $type,
			'name' => $name,
			'data' => $data['data'],
			'pagination' => $this->PaginationtoJson($data['total'], $data['per_page'], $data['current_page'])
		];
	}

	public function PaginationtoJson($total, $limit, $currentPage)
	{
		$this->initPagination($total, $limit, $currentPage);
		return [
			'label' => sprintf('Page %s of %s', $this->getCurrentPage(), $this->pages),
			'currentPage' => $this->getCurrentPage(),
			/*trang hiện thời*/
			'totalRecord' => $this->total,
			/*tổng số record thoả yêu cầu truy vấn từ client*/
			'limit' => $this->limit,
			/*số record mỗi trang*/
		];
	}

	public function getTotal()
	{
		return $this->total;
	}

	public function getRowOffset($idx)
	{
		return $this->curPage * $this->limit + ($idx + 1);
	}

	public function getCurrentPage($from1 = true)
	{
		return $from1 ? $this->curPage + 1 : $this->curPage;
	}

	public function getPages()
	{
		return $this->pages;
	}

	public function __toString()
	{
		return $this->strContent;
	}

	protected function initPagination($total, $limit, $currentPage)
	{
		$this->limit = $limit;
		$this->lmstart = ($currentPage - 1) * $limit;

		$this->pages = ceil($total / $this->limit);

		$this->curPage = $currentPage - 1;

		$this->total = $total;

		$this->buildListFooter();
	}

	protected function buildListFooter()
	{
		if ($this->pages > 1) {
			//build html
			$this->strContent .= '<input type="hidden" name="limit" id="limit" value="' . $this->limit . '">';
			$this->strContent .= '<input type="hidden" name="lmstart" id="lmstart" value="' . $this->lmstart . '">';
			$this->strContent .= '<nav><ul class="pagination">';

			if ($this->pages < $this->maxPages) {
				$fromPage = 0;
				$toPage = $this->pages;
			} else {
				//more than max page
				$fromPage = $this->curPage - ($this->maxPages / 2) > 0 ? $this->curPage - ($this->maxPages / 2) : 0;
				//$toPage 	= $this->curPage+($this->maxPages/2)<$this->pages?$this->curPage+($this->maxPages/2):$this->pages;
				$toPage = $this->pages < $this->maxPages ? $this->pages : ($fromPage + $this->maxPages > $this->pages ? $this->pages : $fromPage + $this->maxPages);
			}

			//first page
			if ($this->curPage == 0) {
				$this->strContent .= '<li class="disabled"><a href="javascript:void(0);">&laquo;</a></li>';
			} else {
				$this->strContent .= '<li><a onclick="$(\'#limit\').val(' . $this->limit . ');$(\'#lmstart\').val(' . ($this->limit * ($this->curPage - 1)) . '); $(\'#tForm\').submit();" href="javascript:void(0);">&laquo;</a></li>';
			}

			for ($i = $fromPage; $i < $toPage; $i++) {
				$pageLabel = $i + 1;
				$limitStart = $this->limit * $i;

				if ($i == $this->curPage) {
					$disable = 'class="active"';
					$onclick = '';
				} else {
					$disable = 'class="litem"';
					$onclick = 'onclick="$(\'#limit\').val(' . $this->limit . ');$(\'#lmstart\').val(' . $limitStart . '); $(\'#tForm\').submit();"';
				}
				$this->strContent .= '<li ' . $disable . '><a ' . $onclick . ' href="javascript:void(0);">' . $pageLabel . '</a></li>';
			}

			//last page
			if ($this->curPage == $this->pages - 1) {
				$this->strContent .= '<li class="disabled"><a href="javascript:void(0);">&raquo;</a></li>';
			} else {
				$this->strContent .= '<li><a onclick="$(\'#limit\').val(' . $this->limit . ');$(\'#lmstart\').val(' . ($this->limit * ($this->curPage + 1)) . '); $(\'#tForm\').submit();" href="javascript:void(0);">&raquo;</a></li>';
			}

			$this->strContent .= '</ul></nav>';
		}
	}
}
