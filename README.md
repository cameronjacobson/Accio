# Accio

Early stage of a PHP client for Voldemort

## see examples in:

examples/*

```examples/partially_working_examples.php```

* NOTE:  There is only one script where I have partially working examples, and even then you have to manually change version numbers, likely along with several bugs.  I think I'm far enough along for this proof of concept to be taken to the next step.  Eventually I'll be cleaning up the example, and building the real implementation under 'src/':

* Not really sure how far this project will go.  The documentation for building clients is sparse, and I was even having issues with limited support in PHP for ProtocolBuffers and some issues I was having with Thrift, and how it was formatting data vs how server was expecting it, so I basically ended up reverse engineering the protocol from the java source.
