<?php
$old = ini_set('error_log','/tmp/trex-error.log');
if($_GET){
	//Set time limit
	$minutes = 5;
	echo set_time_limit($minutes * 60);
	
	//Query Params
	$mod_start_date = pg_escape_string($_GET['start_date']);
	$mod_end_date = pg_escape_string($_GET['end_date']);
	$mod_username = pg_escape_string($_GET['user']);
	$mod_password = pg_escape_string($_GET['pass']);
	
	//Get DB connection
	$host = "dtord01gpv01p.dc.dotomi.net";
	$port = "5432";
	$dbname = "vds_prd";
	
	$cxn = pg_connect("host=$host port=$port dbname=$dbname user=$mod_username password=$mod_password");
	
	if($cxn){
		runQuery($cxn,$mod_start_date,$mod_end_date);
	}
	else
		echo '{"error":"Couldn\'t establish connection to DB"}';
	pg_close($cxn);
}

function runQuery($cxn,$start,$end){
	$query ="SELECT
    ext_advertiser_id,
    ext_campaign_id, 
    SUM(CASE WHEN in_view_field_attr = 'inView' THEN 1 ELSE 0 END) AS displayviewable,
    SUM(CASE WHEN in_view_field_attr = 'outOfView' THEN 1 ELSE 0 END) AS displaynotsviewable,
    SUM(CASE WHEN in_view_field_attr = 'inView'   or in_view_field_attr = 'outOfView' THEN 1 ELSE 0 END) AS displaytotalviewablemeasured,
    Cast(SUM(CASE WHEN in_view_field_attr = 'inView' THEN 1 ELSE 0 END)AS DECIMAL(10,2)) /  Cast(SUM(CASE WHEN in_view_field_attr = 'inView'   or in_view_field_attr = 'outOfView' THEN 1 ELSE Null END)AS DECIMAL(10,2))  as DisplayViewability,
    SUM(CASE WHEN in_view_field_attr = 'N/A' THEN 1 ELSE 0 END) AS displaytotalunmeasured,
    SUM(CASE WHEN sad_score_meas = 1.0 THEN 1 ELSE 0 END) AS suspicious,
    SUM(CASE WHEN sad_score_meas = 0.0 THEN 1 ELSE 0 END) AS notsuspicious,
    SUM(CASE WHEN sad_score_meas = 0.0 or sad_score_meas = 1.0 THEN 1 ELSE 0 END) AS totalmonitored,
    CAST(SUM(CASE WHEN sad_score_meas = 1.0 THEN 1 ELSE 0 END)AS DECIMAL(10,2)) / Cast(SUM(CASE WHEN sad_score_meas = 0.0 or sad_score_meas = 1.0 THEN 1 ELSE null END)  AS DECIMAL(10,2)) AS Suspicious_Activity,
    SUM(CASE WHEN video_ad_in_view_meas = 0.0 THEN 1 ELSE 0 END) AS notvideoviewable,
    SUM(CASE WHEN video_ad_in_view_meas = 1.0 THEN 1 ELSE 0 END) AS videoviewable,
    SUM(CASE WHEN video_ad_in_view_meas = 0.0 or video_ad_in_view_meas = 1.0 THEN 1 ELSE 0 END) AS totalmeasuredvideoviewable,
    CAST( SUM(CASE WHEN video_ad_in_view_meas = 1.0 THEN 1 ELSE 0 END)AS DECIMAL(10,2)) /  CAST(SUM(CASE WHEN video_ad_in_view_meas = 0.0 or video_ad_in_view_meas = 1.0 THEN 1 ELSE null END) AS DECIMAL(10,2)) As VideoViewability,
    SUM(CASE WHEN video_ad_in_view_meas = -1.0 THEN 1 ELSE 0 END) AS notmeasuredvideoviewable          FROM
    whse.raw_trafficscope_log   a
WHERE
	a.view_date BETWEEN '$start' AND '$end'
GROUP BY 1,2
ORDER BY Suspicious_Activity desc
LIMIT 100000;";
		
	$result = pg_query($cxn, $query);
	$report_info = '"Advertiser Name","Advertiser Id","Campaign Name","Ext Advertiser ID",'.
	'"Ext Campaign ID","View Date","Display Viewable","Display Not Viewable","Display Unmeasured Viewable","Not Suspicious",'.
	'"Viewable","Not Video Viewable","Video Viewable","Not Measured Video Viewable","Suspicious"';
	while($row = pg_fetch_row($result)){
		$report_info.="\n";
		$first = true;
		foreach($row as $value){
			$report_info.= ($first ? '' : ',').'"'.$value.'"';
			$first=false;
		}
	}
		
	echo $report_info ? $report_info : '{"error":"No Data Returned"}';
}
?>