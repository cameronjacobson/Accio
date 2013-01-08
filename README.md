# Accio

Early stage of a PHP client for Voldemort

## see examples in:

examples/*

<pre>
examples/partially_working_examples.php
</pre>

##PLEASE NOTE:

* Supported operations: put, get, delete, getversion, and getall.  So based on what I see in the java client source, it appears we've now got full support for all vp0 protocol commands

* There is only one script where I have partially working examples, and even then you have to manually change version numbers, likely along with several bugs.  I think I'm far enough along for this proof of concept to be taken to the next step.  Eventually I'll be cleaning up the example, and building the real implementation under 'src/':

* Wouldn't recommend using this with 32-bit machine.  The binary protocol depends on 8-byte timestamp, which I'm unpacking with 'd' flag.

* Initial tests are promising.  I'm getting on average approx. 0.002 seconds per operation in a single-node setup running both client and server locally on my laptop.

* Be sure to set ```request.format="vp0"``` [voldemort-native protocol version 0] in applicable server.properties file

* With regard to Thrift and Protocol Buffers, the serializer source code files state in comments that they're only meant to be used with java, with other language support possibly coming in the future, so I think at this time native vp0 protocol is the best bet for creating a PHP client.
