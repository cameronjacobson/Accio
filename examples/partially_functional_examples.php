<?php

require_once(dirname(__DIR__).'/vendor/autoload.php');

use SimpleHttpClient\SimpleHttpClient;

define('HOST','localhost');
define('PORT',8081);

$key = 'mykey';
$value = 'myvalue';


$start = microtime(true);
echo 'PUT:'.$key.' '.$value.PHP_EOL;
$response = put('test',$key,$value,256);
echo 'FINISHED IN: '.(microtime(true)-$start).' SECONDS'.PHP_EOL;
//var_dump($response);
//parsePutResponse($response);


$start = microtime(true);
echo 'GET:' .PHP_EOL;
$response = get('test',$key);
echo 'FINISHED IN: '.(microtime(true)-$start).' SECONDS'.PHP_EOL;
parseGetResponse($response);

$start = microtime(true);
$response = delete('test',$key,256);
echo 'FINISHED IN: '.(microtime(true)-$start).' SECONDS'.PHP_EOL;
//parseDeleteResponse($response);


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
	$request.= pack('C', bytesRequired($version));
	$request.= pack('n', 0); // nodeid
	$request.= pack('n', $version);
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
	$request.= pack('N', getSizeInBytes($version)+strlen($value)); // + sizeInBytes

	// START version.toBytes
	$request.= pack('n', 1); // versions.size
	$request.= pack('C', bytesRequired($version));
	$request.= pack('n', 0); // nodeid
	$request.= pack('n', $version);
	$request.= pack('d', microtime(true));
	// END version.toBytes

	$request.= $value;

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
		'port'=> PORT,
		'contentType'=>'application/x-thrift'
	]);
	$response = $client->post('/stores',$serialized);
	return $response['body'];
}

function bytesRequired($number){
	$x=0;
	while($number >= (0x01 << (8*++$x))){}
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
	$statuscode = unpack('n',substr($response,0,2));
	$offset = 2;
	echo 'STATUSCODE: '.$statuscode[1].PHP_EOL;
	$numrecords = unpack('N',substr($response,$offset,4));
	  $offset += 4;
	echo 'NUMRECORDS: '.$numrecords[1].PHP_EOL;

	for($x=0;$x<$numrecords[1];$x++){
		$len = unpack('N',substr($response,$offset,4));
		  $offset += 4;

		$numversions = unpack('n',substr($response,$offset,2));
		  $offset += 2;
		  echo '  NUM VERSIONS: '.$numversions[1].PHP_EOL;

		$versionsize = unpack('c',substr($response,$offset,1));
		  $offset += 1;
		  echo '  VERSION SIZE: '.$versionsize[1].PHP_EOL;

	// LOOP
		$nodeid = unpack('n',substr($response,$offset,2));
		  $offset += 2;
		  echo '    NODE ID: '.$nodeid[1].PHP_EOL;
		$versionsize = unpack('c',substr($response,$offset,1));
		  $offset += 1;
		  echo '    VERSION SIZE: '.$versionsize[1].PHP_EOL;
	// ENDLOOP
		$timestamp = unpack('N/N',substr($response,$offset,8));
		  $offset += 8;
		  echo 'TIMESTAMP: '.$timestamp[1].' '.$timestamp[2].PHP_EOL;

		$value = substr($response,$offset,$len[1]);
		  $offset += strlen($value);
		  echo ' VALUE LENGTH: '.strlen($value).PHP_EOL;
		  echo ' VALUE: '.$value.PHP_EOL.PHP_EOL;

	}
}

