<?php
libxml_use_internal_errors(true);

/*
 * Grab the HTML page and return the DOM
 */
function getDomFromUrl($url){
	echo "Requesting url: $url\n";
	$html = file_get_contents($url);
	$dom = new DomDocument();
	$dom->recover = true;
	$dom->strictErrorChecking = false;
	$dom->loadHTML($html);

	return $dom;
}

/*
 * Grab a list of categories from IPlayer
 * using the url http://www.bbc.co.uk/iplayer
 */
function getIplayerCategories(){
	$categories = array();
	
	// get the categories
	$dom = getDomFromUrl("http://www.bbc.co.uk/iplayer");

	// search for <li> under <div> with class "panel-section genre" 
	// in case we need to use the list-item class (for duplicates)
	$xpath = new DomXPath($dom);
	$nodes = $xpath->query('//div[contains(@class, "panel-section genre")]//li');
	foreach($nodes as $i=>$node){
		$links = $node->getElementsByTagName('a');
		$link = $links->item(0);
		if (!array_key_exists($link->nodeValue, $categories)){
			$categories[$link->nodeValue] = $link->getAttribute('href');
			//debug
			//printf("Node(%s): (%s) [%s] %s\n", $i, $node->getAttribute('class'), $link->getAttribute('href'), $link->nodeValue);
		}
	}
	
	return $categories;
}

/*
 * List the shows in a category and page
 */
function getCatShows($cat, $page=1){
	$shows = array();
	$url = "http://www.bbc.co.uk$cat/all?sort=atoz&page=$page";
	$dom = getDomFromUrl($url);
	$xpath = new DomXPath($dom);
	
	$nodes = $xpath->query('//li[contains(@class, "list-item programme")]');
	foreach($nodes as $i=>$node){
		// link id
		$link = $node->getAttribute('data-ip-id');
		
		// title is in the anchor, div(class="secondary"), div(class="title")
		$tmp = $xpath->query('a/div[contains(@class, "secondary")]/div[contains(@class, "title")]', $node);
		$title = $tmp->item(0)->nodeValue;
		
		// episode count
		$tmp = $xpath->query('div[contains(@class, "view-more-grid")]/a/div/em', $node);
		if ( ($tmp !== false) && ($tmp->item(0) != null) ){
			$episode_count = trim($tmp->item(0)->nodeValue);
			$title .= $episode_count!="" ? "($episode_count episodes)" : "";
			$shows[$title] = "episodes/$link";
		}else{
			$shows[$title] = "episode/$link";
		}
		
	}
	
	//printf("Found %d shows on page %d\n", count($shows), $page);
	return $shows;
}

/*
 * Get a list of pages for the category and request getCatShows for each page
 */
function getShowsInCategory($cat){	
	$shows = array();
	$url = "http://www.bbc.co.uk$cat/all?sort=atoz";
	$dom = getDomFromUrl($url);
	$xpath = new DomXPath($dom);
	
	// do page One:
	$shows = getCatShows($cat);
	
	// Look for <div> with class 'paginate' and count the list items with anchor.
	// Repeat reading the page for each number in the pagination count
	$links = $xpath->query('//div[contains(@class, "paginate")]//li//a');
	foreach($links as $lnk){
		$page = str_replace("page", "", $lnk->nodeValue);
		$page = trim( preg_replace('/\n/','',$page) );
		$shows = $shows + getCatShows($cat, $page);
	}
	
	return $shows;
}

/*
 * Search for a show name
 */
function searchForShow($showName){
	$shows = array();
	$url = "http://www.bbc.co.uk/iplayer/search?q=$showName";
	$dom = getDomFromUrl($url);
	$xpath = new DomXPath($dom);
	
	// search for <a> with class 'list-item-link' and get the title and href attributes
	$nodes = $xpath->query('//a[contains(@class, "list-item-link stat")]');
	foreach($nodes as $i=>$node){
		$title = $node->getAttribute('title');
		$link = $node->getAttribute('href');
		$shows[$title] = $link;
		//printf("Node(%s): %s\n", $i, $title);
	}
	
	return $shows;
}


$cats = getIplayerCategories();
//var_dump($cats);
printf("%d categories found\n", count($cats));


//TEST - get the first category and list the shows
$category_url = reset($cats);
$category_name = key($cats);

$shows = getShowsInCategory($category_url);
//var_dump($shows);
printf("%d shows in Category '%s'\n", count($shows), $category_name);
print_r($shows);

/*
next($cats);
$category_name = key($cats);
$category_url = $cats[$category_name];

$shows = getShowsInCategory($category_url);
//var_dump($shows);
printf("%d shows in Category '%s'\n", count($shows), $category_name);
*/


/*
echo "Searching for 'the fall'\n";
$shows = searchForShow(urlencode("the fall"));
foreach($shows as $k=>$v){
	printf("%s, %s\n", $k, $v);
}
*/
