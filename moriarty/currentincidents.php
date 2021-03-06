<?php
define('XSD_FLOAT', 'http://www.w3.org/2001/XMLSchema#');
define('FOAF', 'http://xmlns.com/foaf/0.1/');
define('DCT','http://purl.org/dc/terms/');
define('GEO','http://www.w3.org/2003/01/geo/wgs84_pos#');
define('MORIARTY_ARC_DIR', 'arc/');
define('TRAFFIC_DATA', 'http://trafficscotland.dataincubator.org/');
define('TRAFFIC_VOCAB', 'http://purl.org/ontologies/road-traffic/');
define('INCIDENT', TRAFFIC_DATA.'incidents/');
require('moriarty/moriarty.inc.php');
require('moriarty/simplegraph.class.php');


$rss = file_get_contents('http://trafficscotland.org/rss/feeds/currentincidents.aspx');
$dom = new DomDocument();
$dom->loadXML($rss);

$graph = new SimpleGraph();

$items = $dom->getElementsByTagName('item');

foreach($items as $item){
	$pubDate  = $item->getElementsByTagName('pubDate')->item(0)->nodeValue;	
	$pubDateTimeStamp = strtotime($pubDate);
	$link = $item->getElementsByTagName('link')->item(0)->nodeValue;
	$uri  = INCIDENT.getIdFromUrl($item->getElementsByTagName('link')->item(0)->nodeValue);
	$geo= $item->getElementsByTagNameNS('http://www.georss.org/georss', 'point' )->item(0)->nodeValue;
	list($lat, $long) = explode(',', $geo);
	
	$label  = $item->getElementsByTagName('title')->item(0)->nodeValue;	
	$description  = $item->getElementsByTagName('description')->item(0)->nodeValue;	
	$Cause = getCauseFromTitle($label);
	$graph->add_resource_triple($uri, RDF_TYPE, TRAFFIC_VOCAB.'Incident');
	foreach(getRoadNamesFromTitle($label) as $roadName){
		$roadUri = TRAFFIC_DATA.'roads/'.$roadName;
		$graph->add_resource_triple($roadUri, OWL_SAMEAS, 'http://transport.data.gov.uk/id/road/'.$roadName);
		$graph->add_resource_triple($roadUri, RDF_TYPE, TRAFFIC_VOCAB.'Road');
		$graph->add_literal_triple($roadUri, RDFS_LABEL, $roadName);
		$graph->add_resource_triple($uri,TRAFFIC_VOCAB.'road', $roadUri);
	}
	
	
	if(strstr($label, 'Cleared:')){
		$graph->add_resource_triple($uri, TRAFFIC_VOCAB.'status', TRAFFIC_DATA.'statuses/cleared');
		$label = str_replace('Cleared:', '', $label);
	}

	foreach(getJunctionsFromTitle($label) as $junction){
		$roadNames = getRoadNamesFromTitle($title);
		$motorwayName = $roadNames[0];
		$junctionURI = TRAFFIC_DATA.'roads/'.$motorwayName.'/junctions/'.$junction;
		$roadUri = TRAFFIC_DATA.'/roads/'.$motorwayName;
		$graph->add_resource_triple($roadUri, TRAFFIC_VOCAB.'juntion', $junctionURI);
		$graph->add_resource_triple($junctionURI, TRAFFIC_VOCAB.'road', $roadUri);
		$graph->add_resource_triple($junctionURI, RDF_TYPE, TRAFFIC_VOCAB.'Junction');
		$graph->add_resource_triple($uri,TRAFFIC_VOCAB.'between', $junctionUri);
	}
	$graph->add_resource_triple($uri,TRAFFIC_VOCAB.'cause', TRAFFIC_VOCAB.$Cause);
	$graph->add_literal_triple($uri, RDFS_LABEL, $label, 'en-gb');
	$graph->add_literal_triple($uri, DCT.'description', $description, 'en-gb');
	$graph->add_literal_triple($uri, GEO.'latitude', $lat, null, XSD_FLOAT);
	$graph->add_literal_triple($uri, GEO.'longitude', $long, null, XSD_FLOAT);
	$graph->add_resource_triple($uri,FOAF.'page', $link);
	$graph->add_literal_triple($uri, TRAFFIC_VOCAB.'reported', date(DATE_W3C,$pubDateTimeStamp));
}

echo $graph->to_turtle();

function getRoadNamesFromTitle($title){
	preg_match_all('/[AM][0-9]+/', $title, $m);
	return $m[0];
}

function getJunctionsFromTitle($title){
	preg_match_all('/J\d{1,2}/', $title, $m);
	return $m;
}

function getIdFromUrl($url){
	preg_match('/id=(\d+)/', $url, $m);
	return $m[1];
}
function getCauseFromTitle($title){

	preg_match('/\w+$/', $title, $m);
	return $m[0];
}


#Cleared: M77 J2 - J3 - Queue
#Cleared: A1 A6093 Haddington - Thistly Cross - High winds
#M8 J26 East - Slip Off - Queue
#Oban and Surrounding Area - Bad Weather
#M8 J10(Westerhs Rd)- J9(Easterhs Rd) - Queue
#A90 M90 J11 Barnhill - St Madoes - Accident
/* 

<incident/63106> 
	 a traffic:Incident ;
	traffic:cause cause:Queue ;
	traffic:road <road/M77> ;
	traffic:between <road/M77/J2> , <road/M77/J2> ;
	rdfs:label "M8 J25 W - E Clyde Tunnel turnoff - Queue"@en-gb ;
	dct:description "3 lanes restricted Westbound indefinitely"@en-gb ;
	foaf:page <http://www.trafficscotland.org/currentincidents/details.aspx?id=63106&amp;type=ci> ;
	geo:lat "55.8544939039651" ;
	geo:long "-4.34304657435104" ;
	dct:date "Wed, 05 Jan 2011 16:57:23 GMT";
	.


 
 */

?>
