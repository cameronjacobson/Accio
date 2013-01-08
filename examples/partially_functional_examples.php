<?php

require_once(dirname(__DIR__).'/vendor/autoload.php');

use SimpleHttpClient\SimpleHttpClient;

define('HOST','localhost');
define('PORT',8081);

$key = 'mykey';
$value = 'myvalue';
$key2 = 'mykey2';
$value2 = 'myvalue2';

$start = microtime(true);

for($x=1;$x<2;$x++){

	echo 'PUT:'.$key.' '.$value.','.$key2.' '.$value2.PHP_EOL;
	$response = put('test',$key,$value,$x);
	$response = put('test',$key2,$value2,$x);
	var_export(parsePutResponse($response) ? 'success' : 'failed');
	echo PHP_EOL;

	echo 'GET:'.$key.','.$key2.PHP_EOL;
	$response = get('test',$key);
	$response = get('test',$key2);
	var_export(parseGetResponse($response));
	echo PHP_EOL;

	echo 'GET VERSION:'.$key.','.$key2.PHP_EOL;
	$response = getversion('test',$key);
	$response = getversion('test',$key2);
	var_export(parseGetVersionResponse($response));
	echo PHP_EOL;

	echo 'GET ALL:'.$key.','.$key2.PHP_EOL;
	$response = getall('test',[$key,$key2]);
	var_export(parseGetAllResponse($response));
	echo PHP_EOL;

	echo 'DELETE: '.$key.','.$key2.PHP_EOL;
	$response = delete('test',$key,$x);
	$response = delete('test',$key2,$x);
	var_export(parseDeleteResponse($response) ? 'success' : 'failed');
	echo PHP_EOL;

}

echo 'FINISHED IN: '.(microtime(true)-$start).' SECONDS'.PHP_EOL;

function parsePutResponse($response){
	$statuscode = unpack('n',substr($response,0,2));
	$offset = 2;
	if(empty($statuscode[1])){
		return true;
	}
	else {
		list($errorcode,$error) = writeException($response,$offset);
		throw new Exception('ERROR: '.$errorcode.' - '.$error);
	}
}

function parseDeleteResponse($response){
	$statuscode = unpack('n',substr($response,0,2));
	$offset = 2;
	if(empty($statuscode[1])){
		return true;
	}
	else {
		list($errorcode,$error) = writeException($response,$offset);
		throw new Exception('ERROR: '.$errorcode.' - '.$error);
	}
}

function parseGetVersionResponse($response){
	$statuscode = unpack('n',substr($response,0,2));
	$offset = 2;
	if(empty($statuscode[1])){
		$numversions = unpack('N',substr($response,$offset,4));
		  $offset += 4;
		$clocks = array();
		for($x=0;$x<$numversions[1];$x++){
			$clocklen = unpack('N',substr($response,$offset,4));
			$offset += 4;
			$clocks[] = getClock(substr($response, $offset, $clocklen[1]),$zero=0);
			$offset += $clocklen[1];
		}
		return $clocks;
	}
	else {
		list($errorcode,$error) = writeException($response,$offset);
		throw new Exception('ERROR: '.$errorcode.' - '.$error);
	}
}

function getversion($store, $key){
	$request = pack('C', 10);
	$request.= pack('n', strlen($store));
	$request.= $store;
	$request.= pack('C', 0);
	$request.= pack('N', strlen($key));
	$request.= $key;

	return send($request);
}

function delete($store, $key, $version){
	$request = pack('C', 3);
	$request.= pack('n', strlen($store));
	$request.= $store;
	$request.= pack('C', 0);
	$request.= pack('N', strlen($key));
	$request.= $key;
	$request.= pack('n', getSizeInBytes($version));

	// START clock.toBytes
	$request.= pack('n', 1);
	$request.= pack('C', $bytesreq = bytesRequired($version));
	$request.= pack('n', 0); // nodeid
	$request.= pack(packVersion($bytesreq),$version);
	$request.= pack('d', microtime(true));
	// END clock.toBytes

	return send($request);
}

function put($storename, $key, $value, $version = 1){

	$request = pack('C', 2);
	$request.= pack('n', strlen($storename));
	$request.= $storename;
	$request.= pack('C', 0);
	$request.= pack('N', strlen($key));
	$request.= $key;
	$request.= pack('N', getSizeInBytes($version)+strlen($value));

	// START version.toBytes
	$request.= pack('n', 1); // versions.size
	$request.= pack('C', $bytesreq = bytesRequired($version));
	$request.= pack('n', 0); // nodeid
	$request.= pack(packVersion($bytesreq),$version);
	$request.= pack('d', microtime(true));
	// END version.toBytes

	$request.= $value;

	return send($request);
}

function packVersion($bytesreq){
	switch($bytesreq){
		case 1:
			$version = 'C';
			break;
		case 2:
			$version = 'n';
			break;
		case 3:
			$version = 'C3';
			break;
		case 4:
			$version = 'N';
			break;
	}
	return $version;
}

function getall($store,Array $keys){
	$request = pack('C',4);
	$request.= pack('n',strlen($store));
	$request.= $store;
	$request.= pack('C',0);
	$request.= pack('N',count($keys));
	foreach($keys as $key){
		$request.= pack('N',strlen($key));
		$request.= $key;
	}
	return send($request);
}

function get($store,$key){
	$request = pack('C',1);
	$request.= pack('n',strlen($store));
	$request.= $store;
	$request.= pack('C',0);
	$request.= pack('N',strlen($key));
	$request.= $key;

	return send($request);
}

function send($serialized){
	$client = new SimpleHttpClient([
		'host'=> HOST,
		'port'=> PORT
	]);
	$response = $client->post('/stores',$serialized);
	return $response['body'];
}

function bytesRequired($number){
    $x=0;
    do{ 
        $tmp = empty($tmp) ? 0xff : (0xff + ($tmp << 8));
        if($tmp === -1){
            throw new Exception('too many bytes required for '.$number);
        }
        $x++;
    } while(($number > $tmp));
    return $x;
}

function getSizeInBytes($version){
	return 2+1+(1*(2+bytesRequired($version)))+8;
}

/*
 FYI

public class VoldemortOpCode {

	public static final byte GET_OP_CODE = 1;
	public static final byte PUT_OP_CODE = 2;
	public static final byte DELETE_OP_CODE = 3;
	public static final byte GET_ALL_OP_CODE = 4;
	public static final byte GET_PARTITION_AS_STREAM_OP_CODE = 5;
	public static final byte PUT_ENTRIES_AS_STREAM_OP_CODE = 6;
	public static final byte DELETE_PARTITIONS_OP_CODE = 7;
	public static final byte UPDATE_METADATA_OP_CODE = 8;
	public static final byte REDIRECT_GET_OP_CODE = 9;
	public static final byte GET_VERSION_OP_CODE = 10;
	public static final byte GET_METADATA_OP_CODE = 11;
}

*/

function parseGetAllResponse($response){

	$statuscode = unpack('n',substr($response,0,2));
	$offset = 2;

	if(empty($statuscode[1])){
		$numkeys = unpack('N',substr($response,$offset,4));
		  $offset += 4;
		$results = array();
		for($x=0;$x<$numkeys[1];$x++){
			$keylen = unpack('N',substr($response,$offset,4));
			  $offset += 4;
			$key = substr($response,$offset,$keylen[1]);
			  $offset += $keylen[1];
			$data = getResult($response,$offset);
			$results[$key] = $data;
		}
		return $results;
	}
	else {
		list($errorcode,$error) = writeException($response,$offset);
		throw new Exception('ERROR: '.$errorcode.' - '.$error);
	}
}

function getResult($response,&$offset){
	$numrecords = unpack('N',substr($response,$offset,4));
	  $offset += 4;
	$return = [];
	for($x=0;$x<$numrecords[1];$x++){
		$len = unpack('N',substr($response,$offset,4)); // length of clock and value
		  $offset += 4;
		$return[$x]['clock'] = getClock($response,$offset);
		$return[$x]['value'] = substr($response,$offset,$len[1]-$return[$x]['clock']['clocklen']);
		$offset += $len[1]-$return[$x]['clock']['clocklen'];
	}
	return $return;
}

function writeException($response,&$offset){
	$errorcode = unpack('n',substr($response,$offset,2));
	  $offset += 2;
	$errorlen = unpack('n',substr($response,$offset,2));
	  $offset += 2;
	$error = substr($response,$offset,$errorlen[1]);
	return array($errorcode[1],$error);
}

function parseGetResponse($response){
	$return = [];

	$statuscode = unpack('n',substr($response,0,2));
	$offset = 2;

	if(empty($statuscode[1])){
		$numrecords = unpack('N',substr($response,$offset,4));
		  $offset += 4;

		for($x=0;$x<$numrecords[1];$x++){
			$len = unpack('N',substr($response,$offset,4)); // length of clock and value
			  $offset += 4;
			$return[$x]['clock'] = getClock($response,$offset);
			$return[$x]['value'] = substr($response,$offset);//,$len[1]-$offset);
		}
		return $return;
	}
	else {
		list($errorcode,$error) = writeException($response,$offset);
		throw new Exception('ERROR: '.$errorcode.' - '.$error);
	}
}

function unpackVersion($versionSize){
	switch($versionSize){
		case 1:
			$version = 'C';
			break;
		case 2:
			$version = 'n';
			break;
		case 3:
			$version = 'C3';
			break;
		case 4:
			$version = 'N';
			break;
	}
	return $version;
}

function getClock($response,&$offset){
	$numentries = unpack('n',substr($response,$offset,2));
	  $clocklen = 2;
	  $offset += 2;
	$versionSize = unpack('C',substr($response,$offset,1));
	  $clocklen += 1;
	  $offset += 1;
	$entrysize = 2 + (int)$versionSize[1];
	$minimumBytes = $offset+2+1+$numentries[1]*$entrysize+8;

	$entries = array();
	for($x=0;$x<$numentries[1];$x++){
		$nodeId = unpack('n',substr($response,$offset,2));
		  $clocklen += 2;
		  $offset += 2;
		$version = unpack(unpackVersion($versionSize[1]),substr($response, $offset, $versionSize[1]));
		  $offset += $versionSize[1];
		  $clocklen += $versionSize[1];
		  $entries[$x]['node'] = $nodeId[1];
		  $entries[$x]['version'] = $version[1];
	}

	$timestamp = unpack('d',substr($response,$offset,8));
	$clocklen += 8;
	$offset+=8;
	return [
		'clocklen'=>$clocklen,
		'numentries'=>$numentries[1],
		'versionsize'=>$versionSize[1],
		'entrysize'=>$entrysize,
		'entries'=>$entries,
		'timestamp'=>$timestamp[1]
	];
}
