<?php

function ConnectToNAO($username, $password)
{
	$fp=fsockopen("nethack.alt.org",23);
	sendTelnetHeader($fp);
	$buf .= getTelnetBuffer($fp);
	fwrite($fp,"l");//l for login
	$buf .= getTelnetBuffer($fp);
	fwrite($fp,$username."\r");
	$buf .= getTelnetBuffer($fp);
	fwrite($fp,$password."\r");
	usleep(1000000);				//password delay
	$buf .= getTelnetBuffer($fp);

	fwrite($fp,"p");				//play
	usleep(50000);
	fwrite($fp,"n");				//no, let me pick race
	usleep(50000);
	fwrite($fp,"b");				//barbarian
	usleep(50000);
	fwrite($fp,"h");				//human
	usleep(50000);
	fwrite($fp,"m");				//male
	usleep(50000);
	fwrite($fp,"n");				//neutral
	usleep(50000);
	fwrite($fp,"\r");				//dismiss the starting message
	usleep(50000);

	return $fp;

}


function getTelnetBuffer($fp)
{
	do                                
	{  
	   $output.=fread($fp, 5000);    // read line by line, or at least small chunks
	   $stat=socket_get_status($fp);
	}
	while($stat["unread_bytes"]);
	return $output;
}

function sendTelnetHeader($fp)
{
	//source for below definitions:
	//http://www.scit.wlv.ac.uk/~jphb/comms/telnet.html
	//and
	//http://www.sans.org/resources/idfaq/fingerp_telnet.php
	
	define("SE", chr(0xF0));	//End of subnegotiation parameters.
	define("SB", chr(0xFA));	//Subnegotiation of the indicated option follows.
	define("WILL", chr(0xFB));	//Indicates the desire to begin performing, or confirmation that you are now performing, the indicated option.
	define("WONT", chr(0xFC));	//Indicates the refusal to perform, or continue performing, the indicated option. 
	define("_DO", chr(0xFD));	
	define("DONT", chr(0xFE));  //Indicates the demand that the other party stop performing, or confirmation that you are no longer expecting the other								//party to perform, the indicated option. 
	define("IAC", chr(0xFF));	//Interpret as command

	define("NEW_ENVIRONMENT_OPTION", chr(0x27));
	
	define("BINARY_TRANSMISSION", chr(0x00));
	define("_ECHO", chr(0x01));
	define("SUPPRESS_GO_AHEAD", chr(0x03));
	define("STATUS", chr(0x05));
	define("TERMINAL_TYPE", chr(0x18));
	define("WINDOW_SIZE", chr(0x1F));
	define("TERMINAL_SPEED", chr(0x20));
	define("REMOTE_FLOW_CONTROL", chr(0x21));
	define("LINEMODE", chr(0x22));
	define("X_DISPLAY_LOCATION", chr(0x23));
	define("ENVIRONMENT_OPTION", chr(0x24));

	$header1=
	IAC.WILL.WINDOW_SIZE.
	IAC.WILL.TERMINAL_SPEED.
	IAC.WILL.TERMINAL_TYPE.
	IAC.WILL.NEW_ENVIRONMENT_OPTION.
	IAC._DO._ECHO.
	IAC.WILL.SUPPRESS_GO_AHEAD.
	IAC._DO.SUPPRESS_GO_AHEAD.
	IAC.WONT.X_DISPLAY_LOCATION.
	IAC.WONT.ENVIRONMENT_OPTION.
	IAC.SB.WINDOW_SIZE.BINARY_TRANSMISSION."P".BINARY_TRANSMISSION.TERMINAL_TYPE.
	IAC.SE.	IAC.SB.TERMINAL_SPEED.BINARY_TRANSMISSION."38400,38400".
	IAC.SE.
	IAC.SB.NEW_ENVIRONMENT_OPTION.BINARY_TRANSMISSION.
	IAC.SE.
	IAC.SB.TERMINAL_TYPE.BINARY_TRANSMISSION."XTERM".
	IAC.SE;

	$header2=
	IAC.WONT._ECHO.
	IAC.WONT.LINEMODE.
	IAC.DONT.STATUS.
	IAC.WONT.REMOTE_FLOW_CONTROL;

	//send the headers
	fputs($fp,$header1);
	usleep(125000);
	fputs($fp,$header2);
	usleep(125000);

	//wait
	usleep(125000);
	usleep(125000);
	usleep(125000);

}
?>