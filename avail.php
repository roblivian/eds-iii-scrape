<?php

/*

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

header( "Content-type: text/xml");

// III bib number.  example: b4000442
$_GET['bib_no'] = trim( $_GET['bib_no']);

// get the json
$items = iii_avail( $_GET['bib_no']);

// start the xml for output
$xml = new SimpleXMLElement('<work-availability></work-availability>');

// go thru the json, and add to the xml
foreach ( $items as $item) {
  $shelfmark_location = $xml->addChild('shelfmark-location-availability');
  $work_id = $shelfmark_location->addChild('work-id', $_GET['bib_no']);
  $shelfmark = $shelfmark_location->addChild('shelfmark', $item['classnumber']);
  $status = $shelfmark_location->addChild('due-date', $item['status']);
  $location = $shelfmark_location->addChild('location', $item['location']);
}

// output the xml
print $xml->asXML();

function iii_avail($bib_no = NULL, $max_return = 100) {

  $max_return = (int)$max_return;

  if ( !$max_return || $max_return > 100) {
    $max_return = 100;
  }

  $base_url = "http://holmes.lib.muohio.edu";

  if ($bib_no) {

    $url = $base_url ."/search?/.". $bib_no ."/.". $bib_no ."/1,1,1,B/holdings";

    $fetched_html = file_get_contents($url);

    // find the bibItems rows  
    $ok = preg_match_all('/<tr  class="bibItemsEntry">(.*?<\/tr>(?:<tr>.*?<\/tr>|))/si', $fetched_html, $bibItemsEntry, PREG_SET_ORDER);

    if ($ok) {

      $j = 0;

      foreach ( $bibItemsEntry as $holding) {

        $j++;

        $field = array();

        // find/parse each td in the row
        $ok = preg_match_all('/<td(.*?<\/td>(?:<td>.*?<\/td>|))/si', $holding[1], $details, PREG_SET_ORDER);

        /*
         * first = location
         * second = callnumber 
         * third = status
         */
        for ( $i = 0; $i < count( $details); $i++) {
          if ( $i == 0) {
            $label = "location";
          } elseif ( $i == 1) {
            $label = "classnumber";
          } elseif ( $i == 2) {
            $label = "status";
          } else {
            $label = "unknown";
          }

          $value = trim(str_replace("&nbsp;", " ", strip_tags($details[$i][0])));

          // add to the json we'll be returning 
          $field[$label] = $value;
          $field['bibno'] = $bib_no;

        }

        // add to the json we'll be returning 
        $items[] = $field;
        
        if ( $j >= $max_return) {
          break;
        }
      }

    } else {

      /*
       * no matches found, so check the possibility of an "ordered" item
       */
      $url = $base_url ."/record=". $bib_no;

      $fetched_html = file_get_contents($url);
  
      // find the bibOrderEntry table row 
      $ok = preg_match_all('/<tr  class="bibOrderEntry">(.*?)<\/tr>/si', $fetched_html, $holding_matches, PREG_SET_ORDER);

      if ( $ok) {
        $field['bibno'] = $bib_no;
        $field['location'] = strip_tags($holding_matches[0][1]);

        $items[] = $field;
      }

    }

    return $items;
  }

}
