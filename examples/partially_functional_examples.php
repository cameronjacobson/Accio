<?php

require_once(dirname(__DIR__).'/vendor/autoload.php');

use SimpleHttpClient\SimpleHttpClient;

define('HOST','localhost');
define('PORT',8081);

$key = 'mykey';
$value = 'myvalue2';


$start = microtime(true);

for($x=0;$x<500;$x++){

//	echo 'PUT:'.$key.' '.$value.PHP_EOL;
	$response = put('test',$key,$value,$x);
//	var_export(parsePutResponse($response));

//	echo 'GET:'.PHP_EOL;
	$response = get('test',$key);
//	var_export(parseGetResponse($response));

//	echo 'DELETE: '.$key.PHP_EOL;
	$response = delete('test',$key,$x);
//	var_export(parseDeleteResponse($response));
}
echo 'FINISHED IN: '.(microtime(true)-$start).' SECONDS'.PHP_EOL;

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

function packVersion($bytesreq, $version){
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

function parseGetResponse($response){
	$return = [];

	$statuscode = unpack('n',substr($response,0,2));
	$offset = 2;
	echo 'STATUSCODE: '.$statuscode[1].PHP_EOL;

	if(empty($statuscode[1])){
		$numrecords = unpack('N',substr($response,$offset,4));
		  $offset += 4;
		echo 'NUMRECORDS: '.$numrecords[1].PHP_EOL;

		for($x=0;$x<$numrecords[1];$x++){
			$len = unpack('N',substr($response,$offset,4)); // length of clock and value
			  $offset += 4;
			$return[$x]['clock'] = getClock($response,$offset);
			$return[$x]['value'] = substr($response,$offset);//,$len[1]-$offset);
		}
		return $return;
	}
	else {
		$errorcode = unpack('n',substr($response,$offset,2));
		  $offset += 2;
		$errorlen = unpack('n',substr($response,$offset,2));
		  $offset += 2;
		$error = substr($response,$offset,$errorlen[1]);
		throw new Exception('ERROR: '.$errorcode[1].' - '.$error[1]);
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
	  $offset += 2;
	$versionSize = unpack('C',substr($response,$offset,1));
	  $offset += 1;
	$entrysize = 2 + (int)$versionSize[1];
	$minimumBytes = $offset+2+1+$numentries[1]*$entrysize[1]+8;

	$entries = array();
	for($x=0;$x<$numentries[1];$x++){
		$nodeId = unpack('n',substr($response,$offset,2));
		  $offset += 2;
		$version = unpack(unpackVersion($versionSize[1]),substr($response, $offset, $versionSize[1]));
		  $offset += $versionSize[1];
		  $entries[$x]['node'] = $nodeId[1];
		  $entries[$x]['version'] = $version[1];
	}

	$timestamp = unpack('d',substr($response,$offset,8));
	  $offset += 8;

	return [
		'numentries'=>$numentries[1],
		'versionsize'=>$versionSize[1],
		'entrysize'=>$entrysize,
		'entries'=>$entries,
		'timestamp'=>$timestamp[1]
	];
}
