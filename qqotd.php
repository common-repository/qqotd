<?php
/*
Plugin Name: Quality Quotes
Plugin URI: http://www.hopkins81.com/archives/2005/08/18/quotation-plugin/
Description: Displays a random Quality Quote Of The Day. Quotes are updated every day courtesy of www.thequotes.com.
Author: David Hopkins
Version: 0.7
Author URI: http://www.hopkins81.com/
*/

/**
 * This class is used to parse an RSS feed from thequotes.com. The RSS 
 * feed contains all sorts of info, but the only stuff we're interested
 * in is the quotations, which are of the form:
 * <item>
 *    <title>$author</title>
 *    <description>$qquote</description>
 *    <link>$link</link>
 * </item>
 */
class RSSParser {

	var $insideitem = false;
	var $tag = "";
	var $author = "";
	var $qquote = "";
	var $link = "";
	var $counter = 0;
	var $path = ABSPATH;

	/**
	 * Default constuctor - creates qquote directory.
	 * @param $home: absolute path to the plugins direcorty
	 * I predict this will be the main source of problems.
	 */
	function RSSParser($home){
		$this->path .= $home;
		if(!@opendir("$this->path")){
			$dir = mkdir("$this->path",0777);
			if(!$dir)
				echo "<p style=\"color:red\"> qquote directory could not be created!</p>";
		}
	}
	
	/**
	 * Sets the status of the insideitem tag variable.
	 * We are only interested if we are inside an "ITEM" tag or not.
	 * @param $parser: PHP's XML parser created in do_qquotes
	 * @param $tagName: Case folded name of the tag we're entering
	 * @param $attrs: We're not interested in these
	 */
	function startElement($parser, $tagName, $attrs) {
		if ($this->insideitem)
			$this->tag = $tagName;
		elseif ($tagName == "ITEM")
			$this->insideitem = true;
	}
	
	/**
	 * This method is invoked when we reach the end of an ITEM tag.
	 * At this point we should have collected, all the info we need
	 * so we can write the quote to a file and clear the content holder
	 * variables.
	 * @param $parser: PHP's XML parser created in do_qquotes
	 * @param $tagName: Case folded name of the tag we're leaving
	 */
	function endElement($parser, $tagName) {
		if ($tagName == "ITEM") {
			@$fp = fopen("$this->path"."quote$this->counter.txt","w");
			if(!$fp)
				echo "<p style=\"color:red\">qquote failed to write!</p>";
			$this->qquote = $this->removeAdds($this->qquote);
			$quote =  "<p><span class=\"qquote\">".htmlspecialchars(trim($this->qquote))."<br />";
			$quote .=  "<span class=\"qauthor\"><a href='".trim($this->link)."'>".htmlspecialchars(trim($this->author))."</a></span></span></p>\n";
			fwrite($fp,$quote);
			fclose($fp);
			$this->author = "";
			$this->qquote = "";
			$this->link = "";
			$this->insideitem = false;
			$this->counter ++;
		}
	}

	/**
	 * At this point we will be inside an ITEM tag, so the
	 * three subsequent tags we care about are TITLE(author), DESCRIPTION(quote)
	 * and LINK. We copy the data from the XML file into object
	 * variables. 
	 * The data is appended as it may be spread over more than one liine.
	 * @param $parser: PHP's XML parser created in do_qquotes
	 * @param $data: The plain text imbetween two tags.
	 */
	function characterData($parser, $data) {
		if ($this->insideitem) {
			switch ($this->tag) {
				case "TITLE":
					$this->author .= $data;
					break;
				case "DESCRIPTION":
					$this->qquote .= $data;
					break;
				case "LINK":
					$this->link .= $data;
					break;
			}
		}
	}

	/**
	 * This function might be slighly frowned upon. The quotationspage.com
	 * have recently started putting adds in the RSS feeds. This completley ruins
	 * showing a random quote on your blog, so I decided to remove any adds from
	 * the RSS file before we save it locally. I tried to ask quotationspage.com
	 * if I there was another way round this:
	 * http://www.quotationspage.com/forum/viewtopic.php?t=3213
	 * but so far I have had no response!
	 * @param $desc: The quote, possible containing adverts
	 * @return The quote, with all adds removed
	 */
	function removeAdds($desc){
		$needle = "<a";
		$pos = strpos($desc,$needle);
		if(!$pos === false){
			$desc = substr($desc,0,($pos-4));
		}
		return $desc;
		}
}//RSSParser

/**
 * This function first checks to see if we already have any saved quotes.
 * If we do, we want to see how long they've been sitting there, longer
 * than 24 hours and its time to update them. 
 * We then pick one at random and send it to the browser.
 */
function do_qquotes(){
	$update = false;
	$home = "wp-content/plugins/qquotes/";
	$path = ABSPATH.$home;
	$filename = $path."quote0.txt";
	$local = @fopen($filename,"r");
	if($local){
		$current_time = time();
		$file_time = filemtime($filename);
		$diff =  $current_time - $file_time;
		if($diff > 86400)
			$update = true;
	}
	else
		$update = true;

	if($update){
		update_qquotes($home);
	}

	$i = rand(0,11);
	$quote = fopen($path."quote$i.txt","r+");
	if(!$quote)
		echo "<p style=\"color:red\">qquote failed to be read!</p>";
	$contents ='';
	while ($data = fread($quote, 4096))
		$contents .= $data;
	echo $contents;
	fclose($quote);

}//do_qquotes

/**
 * This instantiates the RSSParser object, reads 
 * quotes live from the $URL, parses them, then saves them to
 * text files locally.
 * This function is only called at most once every 24 hours, 
 * so as to keep the chaps at quotationpage.com happy.
 */
function update_qquotes($home){
	//Would be nice to have this URL update itself as I'm sure it will change.
	$URL = "http://feeds.feedburner.com/quotationspage/qotd";
	$xml_parser = xml_parser_create();
	$rss_parser = new RSSParser($home);
	//must pass the XML parser a reference to the object
	xml_set_object($xml_parser,&$rss_parser);
	xml_set_element_handler($xml_parser, "startElement", "endElement");
	xml_set_character_data_handler($xml_parser, "characterData");

	$fp = fopen($URL,"r");
	if(!$fp)
		echo "<p style=\"color:red\">Could not open $URL</p>";
	while ($data = fread($fp, 4096))
		xml_parse($xml_parser, $data, feof($fp)) 
		or die(sprintf("XML error: %s at line %d",
		xml_error_string(xml_get_error_code($xml_parser)),
		xml_get_current_line_number($xml_parser)));
	fclose($fp);
	xml_parser_free($xml_parser);
}//update_qquote
?>
