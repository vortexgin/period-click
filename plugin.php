<?php
/*
Plugin Name: API to get click statistic during specific time frame
Plugin URI: github.com/vortexgin/period-click
Description: Define API action 'period-click'
Version: 1.0
Author: Gin Vortex
Author URI: https://www.facebook.com/vortexgin
*/

// Change to true to get extra debugging info on-screen. Must be true or false, cannot be undefined.
define ( "PCE_DEBUG", false );
// Define the separator between bits of information.
define ( "PCE_SEP", ' | ' );
// Some version details, same as at the top of this file, for use in the page footer.
define ( "PCE_REL_VER",  '0.1' );
define ( "PCE_REL_DATE", '2016-08-12' );
// Repository URL.
define ( "PCE_REPO", 'https://github.com/vortexgin/period-click' );
// Get the GMT offset if it is set
define( "PCE_OFFSET", defined( 'YOURLS_HOURS_OFFSET' ) ? YOURLS_HOURS_OFFSET * 60 * 60 : 0 );

// Define custom action "delete"
yourls_add_filter( 'api_action_period_click', 'period_click_function' );

function period_click_function() {
  $arrTimeframe = array('week', 'month', 'year',);
	// Need 'shorturl' parameter
	if( !isset($_REQUEST['shorturl']) ) {
		return array(
			'statusCode' => 400,
			'simple'     => "Need a 'shorturl' parameter",
			'message'    => 'error: missing param',
		);
	}
  // Need 'period' parameter
	if( !isset($_REQUEST['period']) || !is_date($_REQUEST['period'])) {
		return array(
			'statusCode' => 400,
			'simple'     => "Need a 'period' parameter or invalid value. Should (Y-m-d)",
			'message'    => 'error: missing param',
		);
	}
  // Need 'timeframe' parameter
	if( !isset($_REQUEST['timeframe']) || !in_array($_REQUEST['timeframe'], $arrTimeframe)) {
		return array(
			'statusCode' => 400,
			'simple'     => "Need a 'timeframe' parameter or invalid value. Should (week|month|year)",
			'message'    => 'error: missing param',
		);
	}

  $shorturl = $_REQUEST['shorturl'];
  $exp = explode('/', $shorturl);
  $keyword = $exp[count($exp) - 1];
  $period = $_REQUEST['period'];
  $type = $_REQUEST['timeframe'];

  global $ydb;
  $sql = "SELECT b.url AS longurl, b.title as title
          FROM " . YOURLS_DB_TABLE_URL . " b
          WHERE b.keyword = '" . $keyword. "'";
  $results = $ydb->get_results( $sql );
  if(!$results){
    return array(
      'statusCode' => 404,
      'simple '    => 'Error: short URL not found',
      'message'    => 'error: not found',
    );
  }
  $link = current($results);

  // Test for each $type, create $from and $to date bounds accordingly.
  $end = new DateTime($period);
  $to = $end->format("Y-m-d 23:59:59");

  $start = new DateTime($period);
  $start->sub(new DateInterval('P7D'));
  $from = $start->format("Y-m-d 00:00:00");

  $interval = 'P1D';
  $dateFormat = 'Y-m-d';
  $mysqlDateFormat = '%Y-%m-%d';
  if ( $type == 'month' ) {
    $start = new DateTime($period);
    $start->sub(new DateInterval('P1M'));
    $from = $start->format("Y-m-d 00:00:00");
  } else if ( $type == 'year' ) {
    $start = new DateTime($period);
    $start->sub(new DateInterval('P1Y'));
    $from = $start->format("Y-m-d 00:00:00");

    $interval = 'P1M';
    $dateFormat = 'Y-m';
    $mysqlDateFormat = '%Y-%m';
  }
  $period = new DatePeriod($start, new DateInterval($interval), $end);

  $sql = "SELECT  date_format(a.click_time, '" . $mysqlDateFormat . "') as tanggal, a.shorturl AS shorturl, COUNT(*) AS clicks,
                  b.url AS longurl, b.title as title
          FROM " . YOURLS_DB_TABLE_LOG . " a, " . YOURLS_DB_TABLE_URL . " b
          WHERE a.shorturl = b.keyword
              AND a.shorturl = '" . $keyword . "'
              AND click_time >= '" . $from . "'
              AND click_time <= '" . $to . "'
          GROUP BY tanggal
          ORDER BY tanggal DESC;";
  if ( $results = $ydb->get_results( $sql ) ) {
    $stat = array();
    foreach ( $period as $dt ){
      $value = array(
        'day' => $dt->format($dateFormat),
        'click' => 0,
      );
      foreach($results as $result){
        if($dt->format($dateFormat) == $result->tanggal){
          $value['click'] = $result->clicks;
          break;
        }
      }

      $stat[] = $value;
    }

    return array(
      'statusCode'  => 200,
      'message'     => array(
        'shorturl'  => $shorturl,
        'longurl'   => $link->longurl,
        'title'     => $link->title,
        'stat'      => $stat,
      ),
    );
  }else{
    $stat = array();
    foreach ( $period as $dt ){
      $stat[] = array(
        'day' => $dt->format($dateFormat),
        'click' => 0,
      );
    }

    return array(
      'statusCode'  => 404,
      'message'     => array(
        'shorturl'  => $shorturl,
        'longurl'   => $link->longurl,
        'title'     => $link->title,
        'stat'      => $stat,
      ),
    );
  }
}

function is_date($date){
  list($y, $m, $d) = explode("-", $date);
  if(checkdate($m, $d, $y)){
    return true;
  }

  return false;
}
