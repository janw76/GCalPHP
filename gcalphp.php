<?php

//v1.40: ...and now we have a cache, thanks to Emilio Velis who got @ramayac to
//       write it for him. Which is excellent. Updated 21 February 2011
//v1.30: Daylight savings time *is*, honestly, now properly dealt with.
//       thanks Kevin Grewohl for pointing out that it wasn't! ;)
//       Updated 21 February 2011
//v1.20: Daylight savings time is now properly dealt with
//v1.10: This code now deals correctly with events that have a date range lasting more
//       than one day. Thank you, David Power, for the fix!
//v1.01: Some small bugfixes//30 Jun 2010
//v1.00: Rewrite to remove SimplePie completely, and simply use PHP's inbuilt XML parser.
//       Updated 29 June 2010
//v0.93: Added "make email addresses clickable". Thank you, Bjorn!
//v0.92: Fixed an issue with 'a section of dates' in amendable code. Thank you Kevin!
//v0.91: Nice error message if there are no events to display, requested by Tomas. Thanks!
//v0.90: Feature: clickable links in descriptions (start them http://). Thank you, Adam!
//       Feature: display end times, requested by Lucy. Thanks!
//       Feature: group by date, requested by Lucy. Thanks!
//       http://james.cridland.net/code


/////////
//Configuration
//

// Your private feed - which you get by right-clicking the 'xml' button in the 'Private Address' section of 'Calendar Details'.
if (!isset($calendarfeed)) {$calendarfeed = "https://www.google.com/calendar/feeds/cridland.net_9nfm5orp01h05bnhtd0j0og5g0%40group.calendar.google.com/private-239a285fcf327d9ba6b1f20712498a6a/basic"; }

// Date format you want your details to appear
$dateformat="j F Y"; // 10 March 2009 - see http://www.php.net/date for details
$timeformat="g.ia"; // 12.15am

// The timezone that your user/venue is in (i.e. the time you're entering stuff in Google Calendar.) http://www.php.net/manual/en/timezones.php has a full list
date_default_timezone_set('Europe/London');

// How you want each thing to display.
// By default, this contains all the bits you can grab. You can put ###DATE### in here too if you want to, and disable the 'group by date' below.
$event_display="<P><B>###TITLE###</b> - from ###FROM### ###DATESTART### until ###UNTIL### ###DATEEND### (<a href='###LINK###'>add this</a>)<BR>###WHERE### (<a href='###MAPLINK###'>map</a>)<br>###DESCRIPTION###</p>";

// What happens if there's nothing to display
$event_error="<P>There are no events to display.</p>";

// The separate date header is here
$event_dateheader="<P><B>###DATE###</b></P>";
$GroupByDate=true;
// Change the above to 'false' if you don't want to group this by dates.

// ...and how many you want to display (leave at 999 for everything)
$items_to_show=999;

// ...and here's where you tell it to use a cache.
// Your PHP will need to be able to write to a file called "gcal.xml" in your root. Create this file by SSH'ing into your box and typing these three commands...
// > touch gcal.xml
// > chmod 666 gcal.xml
// > touch -t 01101200 gcal.xml
// If you don't need this, or this is all a bit complex, change this to 'false'
$use_cache=true;

// And finally, change this to 'true' to see lots of fancy debug code
$debug_mode=false;

//
//End of configuration block
/////////

if ($debug_mode) {error_reporting (E_ALL); echo "<P>Debug mode is on.</p>";}

// Form the XML address.
$calendar_xml_address = str_replace("/basic","/full?singleevents=true&futureevents=true&orderby=starttime&sortorder=a",$calendarfeed); //This goes and gets future events in your feed.

if ($debug_mode) {
echo "<P>We're going to go and grab <a href='$calendar_xml_address'>this feed</a>.<P>";}

if ($use_cache) {
        ////////
        //Cache
        //
       
        $cache_time = 3600*12; // 12 hours
        $cache_file = $_SERVER['DOCUMENT_ROOT'].'/gcal.xml'; //xml file saved on server
       
        if ($debug_mode) {echo "<P>Your cache is saved at ".$cache_file."</P>";}
       
        $timedif = @(time() - filemtime($cache_file));
 
        $xml = "";
        if (file_exists($cache_file) && $timedif < $cache_time) {
                if ($debug_mode) {echo "<P>I'll use the cache.</P>";}
                $str = file_get_contents($cache_file);
                $xml = simplexml_load_string($str);
        } else { //not here
                if ($debug_mode) {echo "<P>I don't have any valid cached copy.</P>";}
                $xml = simplexml_load_file($calendar_xml_address); //come here
                if ($f = fopen($cache_file, 'w')) { //save info
                        $str = $xml->asXML();
                        fwrite ($f, $str, strlen($str));
                        fclose($f);
                        if ($debug_mode) {echo "<P>Cache saved :)</P>";}
                } else { echo "<P>Can't write to the cache.</P>"; }
        }
       
        //done!
} else {
	$xml = simplexml_load_file($calendar_xml_address);
}

if ($debug_mode) {echo "<P>Successfully got the GCal feed.</p>";}

$items_shown=0;
$xml->asXML();

foreach ($xml->entry as $entry){
	$ns_gd = $entry->children('http://schemas.google.com/g/2005');

	//Do some niceness to the description
	//Make any URLs used in the description clickable: thanks Adam
	$description = eregi_replace('(((f|ht){1}tp://)[-a-zA-Z0-9@:%_\+.~#?,&//=]+)','<a href="\\1">\\1</a>', $entry->content);
	// Make email addresses in the description clickable: thanks, Bjorn
	$description = eregi_replace('([_.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,3})','<a
href="mailto:\\1">\\1</a>', $description);

	if ($debug_mode) { echo "<P>Here's the next item's start time... GCal says ".$ns_gd->when->attributes()->startTime." PHP says ".date("g.ia  -Z",strtotime($ns_gd->when->attributes()->startTime))."</p>"; }

	// These are the dates we'll display
	$gCalDate = date($dateformat, strtotime($ns_gd->when->attributes()->startTime)+date("Z",strtotime($ns_gd->when->attributes()->startTime)));
	$gCalDateStart = date($dateformat, strtotime($ns_gd->when->attributes()->startTime)+date("Z",strtotime($ns_gd->when->attributes()->startTime)));
	$gCalDateEnd = date($dateformat, strtotime($ns_gd->when->attributes()->endTime)+date("Z",strtotime($ns_gd->when->attributes()->endTime)));
	$gCalStartTime = gmdate($timeformat, strtotime($ns_gd->when->attributes()->startTime)+date("Z",strtotime($ns_gd->when->attributes()->startTime)));
	$gCalEndTime = gmdate($timeformat,strtotime($ns_gd->when->attributes()->endTime)+date("Z",strtotime($ns_gd->when->attributes()->endTime)));
                   
	// Now, let's run it through some str_replaces, and store it with the date for easy sorting later
	$temp_event=$event_display;
	$temp_dateheader=$event_dateheader;
	$temp_event=str_replace("###TITLE###",$entry->title,$temp_event);
	$temp_event=str_replace("###DESCRIPTION###",$description,$temp_event);

	if ($gCalDateStart!=$gCalDateEnd) {
	//This starts and ends on a different date, so show the dates
	$temp_event=str_replace("###DATESTART###",$gCalDateStart,$temp_event);
	$temp_event=str_replace("###DATEEND###",$gCalDateEnd,$temp_event);
	} else {
	$temp_event=str_replace("###DATESTART###",'',$temp_event);
	$temp_event=str_replace("###DATEEND###",'',$temp_event);
	}

	$temp_event=str_replace("###DATE###",$gCalDate,$temp_event);
	$temp_dateheader=str_replace("###DATE###",$gCalDate,$temp_dateheader);
	$temp_event=str_replace("###FROM###",$gCalStartTime,$temp_event);
	$temp_event=str_replace("###UNTIL###",$gCalEndTime,$temp_event);
	$temp_event=str_replace("###WHERE###",$ns_gd->where->attributes()->valueString,$temp_event);
	$temp_event=str_replace("###LINK###",$entry->link->attributes()->href,$temp_event);
	$temp_event=str_replace("###MAPLINK###","http://maps.google.com/?q=".urlencode($ns_gd->where->attributes()->valueString),$temp_event);
	// Accept and translate HTML
	$temp_event=str_replace("&lt;","<",$temp_event);
	$temp_event=str_replace("&gt;",">",$temp_event);
	$temp_event=str_replace("&quot;","\"",$temp_event);
                   
	if (($items_to_show>0 AND $items_shown<$items_to_show)) {
                if ($GroupByDate) {if ($gCalDate!=$old_date) { echo $temp_dateheader; $old_date=$gCalDate;}}
		echo $temp_event;
		$items_shown++;
	}
}

if (!$items_shown) { echo $event_error; }
?>