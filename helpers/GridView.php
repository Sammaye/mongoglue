<?php

namespace mongoglue\helpers;

/**
 * GridView Helper
 * 
 * @example
 * <div style='width:970px; margin:auto; margin-top:20px;'>
	<?php $l = new \mongoglue\helpers\GridView(); $l->attributes(array(
		'cursor' => $db->select('user')->search(),
		'columns' => array(
			array(
				'type' => 'checkbox'
			),
			'_id' => 'ID',
			'username',
			'email',
			'group',
			array(
				'label' => 'Date Created',
				'value' => 'date("d-m-Y H:i:s", !empty($doc->ts) ? $doc->ts->sec : time())'
			),
			array(
				'type' => 'button'
			)
		)
	)); $l->render(); ?>
</div>
 */
class GridView{

	public $id; // The id of the widget, mostly used for AJAX and JQuery stuff

	public $cursor;

	public $columns;
	public $filter;

	public $url; // Put in the full URL of where all the links should point, normally back to the page where they came from
	
	/**
	 * @example array('upload_date' => -1)
	 */
	public $sort; // This will either be current sort from $_GET or default sort
	public $sortableAttributes; 

	public $enableSorting = true;
	public $enablePagination = true;

	public $enableAjaxPagination = false;
	public $ajaxData; // Additional Ajax GET Data
	public $ajaxUrl; // The URL for Ajax Paging

	/**
	 * Additional data to be passed to the view when rendering
	 */
	public $data;

	public $template = "{table}{pager}";

	public $page = 1;
	public $pageSize = 20;
	public $maxPage; // Max number of available pages

	public $tableCssClass = 'grid-view';
	public $rowCssClassExpression;
	public $sorterCssClass;
	public $pagerCssClass;

	protected $itemCount = 0;
	protected $pageItemCount = 0; // This is the amount of items really on the page
	
	private $currentRow = 0;

	function __construct(){
		$this->pageSize = isset($_GET['pagesize']) && $_GET['pagesize'] > 0 ? $_GET['pagesize'] : 20;
		$this->page = isset($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1;
	}	
	
	/**
	 * Populates the class attributes
	 * @param array $a
	 */
	function attributes($a){
		if($a){
			foreach($a as $k=>$v){
				$this->{$k} = $v;
			}
		}
	}	
	
	function pages(){
		return $this->maxPage;
	}

	function render(){

		if(!$this->cursor instanceof GMongoCursor)
			trigger_error("You must supply a GMongoCursor for the cursor param of the GListView widget");

		$this->itemCount = $this->cursor->count();

		if($this->enableSorting){
			// @TODO Add a filter object which can use sorting
			if($this->sort!==null)
				$this->cursor->sort($this->sort);
		}

		// Get the current page
		if($this->enablePagination){
			// Double check current page and make amendmants if needed
			$this->maxPage = ceil($this->itemCount / $this->pageSize) < 1 ? 1 : ceil(($this->itemCount) / $this->pageSize);
			if($this->page < 0 || $this->maxPage < 0){
				$this->maxPage = 1;
				$this->page = 1;
			}

			if($this->page > $this->maxPage) $this->page = $this->maxPage;
			$this->cursor->skip(($this->page-1)*$this->pageSize)->limit($this->pageSize);

			$pager = $this->__renderPager();
			$html = preg_replace("/{pager}/", $pager, $this->template);
		}

		ob_start();
			$this->renderTable();
			$items = ob_get_contents();
		ob_end_clean();

		$html = preg_replace("/{table}/", $items, $html);
		echo $html;
	}

 	function __renderPager(){

 		//$this->maxPage = 10;

		$start = $this->page - 5 > 0 ? $this->page - 5 : 1;
		$end = $this->page + 5 <= $this->maxPage ? $this->page + 5 : $this->maxPage;
		$ret = "";

		$ret .= "<div class='GListView_Pager {$this->pagerCssClass}'>";

	    if($this->page != 1 && $this->maxPage > 1) {
	    	if($this->enableAjaxPagination){
	        	$ret .= '<div class="control"><a href="#page_'.($this->page-1).'">Previous</a></div>';
	    	}else{
	        	$ret .= '<div class="control"><a href="'.$this->getUrl(array('page' => $this->page-1)).'">Previous</a></div>';
	    	}
	    }

	    if($this->maxPage > 1){
	    	$ret .= '<ul>';
		    for ($i = $start; $i <= $end && $i <= $this->maxPage; $i++){

		        if($i==$this->page) {
		        	$ret .= '<li><div class="active" data-page="'.$i.'" style="margin-right:6px;"><span>'.$i.'</span></div></li>';
		        } else {
		        	if($this->enableAjaxPagination){
		            	$ret .= '<li><a style="margin-right:6px;" href="#page_'.($i).'"><span>'.$i.'</span></a></li>';
		        	}else{
		            	$ret .= '<li><a style="margin-right:6px;" href="'.$this->getUrl(array('page' => $i)).'"><span>'.$i.'</span></a></li>';
		        	}
		        }
		    }
		    $ret .= '</ul>';
	    }

	    if($this->page < $this->maxPage) {
	    	if($this->enableAjaxPagination){
				$ret .= '<div class="control"><a href="#page_'.($this->page+1).'">Next</a></div>';
	    	}else{
				$ret .= '<div class="control"><a href="'.$this->getUrl(array('page' => $this->page+1)).'">Next</a></div>';
	    	}
	    }

	    $ret .= "</div>";
	    return $ret;
	}
	
	function renderTableHeader(){
		echo "<thead><tr>";
			foreach($this->columns as $k => $v){
	
				$label = $v;
				if(is_array($v)){
					$label = isset($v['label']) ? $v['label'] : '';
					
					if(isset($v['type']) && $v['type'] == 'checkbox'){
						if(isset($v['showHeader']) && $v['showHeader'] === false){ }else{
							$label = '<input type="checkbox" value="1" id="'.$this->getId().'_cl_all" name="'.$this->getId().'_cl_all"/>';							
						}
					}
					
				}elseif(is_numeric($k)){
					$label = ucwords(str_replace('_', ' ', $v));
				}					
				echo html::openTag('th', array('class' => null)) . $label . html::closeTag('th');
			}
		echo "</tr>";
			
		if($this->filter!==null){
			echo "<tr>";
				foreach($this->columns as $k => $v){
								
				}					
			echo "</tr>";	
		}
		echo "</thead>"; 		
	}
	
	function renderTableBody(){		
		echo "<tbody>";
			foreach($this->cursor as $_id => $doc){
				
				if($this->rowCssClassExpression===null) $this->rowCssClassExpression = 	'$i%2!=0 ? "grid-odd-row" : ""';
				$classString = $this->evaluateExpression($this->rowCssClassExpression, array('doc' => $doc, 'i' => $this->currentRow));
				if(strlen($classString) > 0) $classString = ' class="'.$classString.'"';
				
				echo "<tr$classString>";
				foreach($this->columns as $k => $v){
					echo "<td>{$this->getColumnValue($doc, $k, $v)}</td>";
				}
				echo "</tr>";
				$this->currentRow++;
			}
		echo "</tbody>";
	}

	function renderTable(){
		echo '<table id="'.$this->getId().'" class="'.$this->tableCssClass.'">';
			$this->renderTableHeader();
			$this->renderTableBody();
			echo "<tfoot></tfoot>";
		echo "</table>";
	}

	function getUrl($morph = array()){
		return $this->url . '?' . implode(
				'&amp;', array_merge(
						$this->data, array_merge(
							array(
								//"mode"=>urlencode($this->mode),
								"pagesize"=>$this->pageSize,
								"page"=>$this->page,
								"sorton"=>$this->currentSortAttribute,
								"orderby"=>$this->currentSortOrder
							), $morph
						)
					)
			);
	}
	
	public function getColumnValue($doc, $k, $v){
		if(is_string($v) || (is_numeric($k) && !is_array($v))){
			$value = $doc->getAttribute(is_numeric($k) ? $v : $k);
		}else{ // This column is a lot of complex
			if(isset($v['type'])){
				switch($v['type']){
					case "button":
						$value = $this->getButtonColumnValue($doc, $v);
						break;
					case "checkbox":
						$value = $this->getCheckboxColumnValue($doc, $v);
						break;
				}
			}else{
				$value = $this->evaluateExpression(isset($v['value']) ? $v['value'] : null, array('doc' => $doc));
			}
		}
		return $value;		
	}
	
	public function evaluateExpression($_expression_,$_data_=array()){
		if(is_string($_expression_)){
			extract($_data_);
			return eval('return '.$_expression_.';');
		}else{
			$_data_[]=$this;
			return call_user_func_array($_expression_, $_data_);
		}
	}

	function getButtonColumnValue($doc, $opts = array()){
		
		$opts = array_merge(array(
			'template' => '{update} {delete}',
			'buttons' => array(
				'update' => array(
					'label' => 'Update',
					'image' => null,
					// Please try and find something better than PHP_SELF to use I just put this in quickly
					'url' => '$_SERVER["PHP_SELF"] . "?" . "action=update&amp;id=$doc->_id"',
					'visible' => null
				),
				'delete' => array(
					'label' => 'Delete',
					'image' => null,
					'url' => '$_SERVER["PHP_SELF"] . "?" . "action=deletee&amp;id=$doc->_id"',
					'visible' => null
				)		
			)				
		), $opts);

		$html = $opts['template'];
		foreach($opts['buttons'] as $k => $v){
			
			$buttonHtml = '';
			if(isset($v['visible']) && $v['visible']!==null && !$this->evaluateExpression($v['visible'], array('doc' => $doc))){ }else{
				// Lets proceed getting the button
				$buttonHtml .= '<a class="'.$k.'" href="'.$this->evaluateExpression(isset($v['url']) ? $v['url'] : null, array('doc' => $doc)).'">';
				
				$clearTextId = ucwords(str_replace('_', ' ', $k));
				if(isset($v['label']) && $v['label'] !== null){
					$buttonHtml .= $v['label'];
				}elseif(isset($v['image']) && $v['image'] !== null){
					$buttonHtml .= '<img alt="'.$clearTextId.'" src="'.$v['image'].'"/>';
				}else{
					$buttonHtml .= $clearTextId;
				}
				
				$buttonHtml .= '</a>';
			}
			
			$html = preg_replace('/{'.$k.'}/', $buttonHtml, $html);
		}
		return $html;
	}
	
	function getCheckboxColumnValue($doc, $opts = array()){
		$opts = array_merge(array('checked' => null), $opts);
		return '<input type="checkbox" value="'.$doc->_id.'" id="'.$this->getId().'_cl_'.$this->currentRow.'" name="'.$this->getId().'_cl[]" '.(
			$opts['checked'] !== null && $this->evaluateExpression($opts['checked'], array('doc' => $doc, 'i' => $this->currentRow)) ? 'checked="checked" ' : ''		
		).'/>';
	}

	function end(){}
}