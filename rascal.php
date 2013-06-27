<?php
/*
This project is designed to connect to the nethack.alt.org telnet server and login using username and password specified in login.php.  
It will also open a file and dump the entire telnet session to it for debugging purposes.
*/

/* this code is copied in part from www.php.net and from http://www.geckotribe.com/php-telnet/ */
/* script runs from php command prompt */
/* by Scott Morken aka Nekrom */

include("NAOtelnet.php");	//has the telnet header and some nethack specific functions
include("vt102.php");
include("login.php");		//contains definition of $username and $password

$dumpfile = fopen("C:/rascal/dumplog.txt","w+");//log of the raw vt102 for debugging
$commandfile = fopen("c:/rascal/command_file.txt", "w+");//log file of the actions and results of this script
fclose($dumpfile);
fclose($commandfile);

$fp = ConnectToNAO($username, $password);
$play = TRUE;
$turns = 0;
$hungry = 0;
$hurt = 0;
$weak = 0;
$fainting = 0;
$normal = 1;
$turncap = 0;
$turnmax = 20;
$pray_turn = -100;
$stuck_check = 0;
$enhance_turn = 20;//number of iterations between opening the enhance skills screen, both enhancing skills and refreshing the screen

while($play)
{
	$dumpfile = fopen("C:/rascal/dumplog.txt","a");//log of the raw vt102 for debugging


	$buffer = getTelnetBuffer($fp);
	fwrite($dumpfile, $buffer);//log the raw vt102 for debugging
	
	$nethackData = parseVT102($buffer, $nethackData[0], FALSE);//returns a 2d array containing Nethack's ascii characters (24x80)
																		 //the FALSE indicates to not make detailed reports
	$turns++;
	$NHturn = GetNHturn($rowstr);
	$status_string = "\n\nIteration:".$turns." NHturn:".$NHturn." \nBotstatus: Hungry:".$hungry." hurt:".$hurt." weak:".
						$weak." fainting:".$fainting." normal:".$normal."\nCommands: ";

	logCommand( $status_string);
	//bot status check
	is_hungry($buffer);
	unset($HP);
	if(($HP = GetHP($rowstr)))
	{
		logCommand("GetHP HP:".$HP[0]."/".$HP[1]."=".$HP[2]."%");
		is_hurt($HP);
	}

	is_weak($buffer);
	is_fainting($buffer);
	is_normal();
	
	$myPosition = $nethackData[1];
	logCommand("My Position:".$myPosition[0].",".$myPosition[1]);

	if(BoolComparePosition($myPosition, $lastPostion))
	{
		$stuck_check++;
	}
	else
	{
		$stuck_check=0;
	}

	$lastPostion = $myPosition;
	if($stuck_check>3)
	{
		$direction = rand(1,9);
		if($direction !=5)
			send($direction);
		$stuck_check = 0;
	}

	for($y=0; $y<25; $y++)
	{
		unset($rowstr[$y]);
		for($x=0; $x<80; $x++)
		{
			echo $nethackData[0][$x][$y];//display the nethack screen to the console window
			$rowstr[$y] .= $nethackData[0][$x][$y];  
			//copy the nethack screen to an array of strings each row = 1 string..this is a more sensible way to think about it
		}
	}
	if(stripos($buffer, 'there is a staircase down here'))//this is for when the stairs are covered
	{
		$Downstairs[0] = $myPosition[0];
		$Downstairs[1] = $myPosstion[1];
		logCommand("downstairs found:".$Downstairs[0].",".$Downstairs[1]);
	}
	if(stripos($buffer, 'more'))
	{
		logCommand("respond to --more-- ");
		send("\r");//respond to --more--
	}
	else if(stripos($buffer, 'really attack the'))
	{
		logCommand("found a peaceful ");
		send('y');//oh hell kill it until the bot is upgraded
	}
	else if(stripos($buffer, 'Do you want to add to the current engraving?'))
	{
		send('y');
	}
	else if(stripos($buffer, 'What do you want to write in the dust here?') && $turns > 10)
	{
		if($hurt)
		{
			send("ElberethElberethElberethElberethElberethElberethElberethElbereth\r");
		}
		else
		{
			send("\r");
			logCommand("false engrave 1");
		}
	}
	else if(stripos($buffer, 'What do you want to add to the writing in the dust here?') && $turns > 10)
	{
		if($hurt)
		{
			send("ElberethElberethElberethElberethElberethElberethElberethElbereth\r");
		}
		else
		{
			send("\r");
			logCommand("false engrave 2");
		}
	}
	else if($myPosition[0] < 2)//buggy cursor position
	{
		send(";");
		send(";");//fix the cursor position
		logCommand("fix cursor");
	}
	else if($turns>=$enhance_turn)
	{
		send("#e\ra a\r");
		$enhance_turn = $turns + 20;
		logCommand("enhance turn");
	}
	else
	{
		if($fainting)
		{
			logCommand("fainting ");
			if( ($turns - $pray_turn) > 100)//dont get wrathed
			{
				logCommand("PRAY ");
				$pray_turn = $turns;
				send("#");//open extended commands
				send("p\r");//p is pray shortcut
				send("y");//yes i want to pray fool
			}
			else 
			{
				$direction = explore();//uh oh
				logCommand("exploring:".$direction);
			}

		}
		else if($normal || $hungry || $weak)
		{
			//command bot to do things here
			if(!isset($Downstairs[0]))
				$Downstairs = find(0);
			if($Downstairs != 0)
			{
				logCommand("downstairs:".$Downstairs[0].",".$Downstairs[1]." type:'".$rowstr[$$Downstairs[0]][$$Downstairs[1]]."'");
				$vector = GetVector($myPosition, $Downstairs);
				logCommand("got vector to >: dist:".$vector[0]." angle:".$vector[1]);
			}
			else
			{
				logCommand("did not find downstairs");
			}
			$closestTreasure = find(1);
			if($closestTreasure!=0)
			{
				logCommand("treasure:".$closestTreasure[0].",".$closestTreasure[1]." type:'".$rowstr[$closestTreasure[0]][$closestTreasure[1]]."'");
				$vector = GetVector($myPosition, $closestTreasure);
				logCommand("got vector to treasure: dist:".$vector[0]." angle:".$vector[1]);
			}
			else
			{
				logCommand("did not find treasure");
			}
			$closestMonster = find(2);
			if($closestMonster!=0)
			{
				logCommand("monster:".$closestMonster[0].",".$closestMonster[1]." type:'".$rowstr[$closestMonster[0]][$closestMonster[1]]."'");
				$vector = GetVector($myPosition, $closestMonster);
				logCommand("got vector to monster: dist:".$vector[0]." angle:".$vector[1]);
				$move = GetCloserTo($myPosition, $closestMonster, $rowstr );
				logCommand("getting closer to monster:".$move);
			}
			else
			{
				logCommand("did not find monster");
			}
			$direction = explore();
			logCommand("exploring:".$direction);
		}
		else if($hurt && $turns > 10)
		{
			send("E");//engrave
			send("-");//fingers
			//this will start the engraving...next cycle will finish it
			logCommand("engraving:");
		}
		else
		{
			$direction = explore();
			logCommand("exploring:".$direction);
		}
	}
	if(stripos($buffer, 'do you want your'))
	{	
		logCommand("death ");
		send("q");//quit
		usleep(1000000);//viewing delay
		send("q");//quit i say! dont freeze!
		$play=FALSE;
	}
	if($turns>$turnmax && $turncap)
	{
		send("S");//save
		send("y");
		$play=FALSE;
	}
	
	fclose($dumpfile);
}


fclose($fp);



function is_hungry($buffer)
{
	global $hungry;

	if(stripos($buffer, 'hungry'))
		$hungry = 1;//find food and eat it
	else
		$hungry = 0;
}
function is_hurt($HP)
{	//if hurt
	//get away from monsters
	global $hurt;

	if($HP[2] < 20 && isset($HP[2]))//percent
		$hurt = 1;
	else
		$hurt = 0;
}
function is_weak($buffer)
{
	global $weak;

	if(stripos($buffer, 'weak'))
		$weak = 1;//find food and eat it critical!
	else
		$weak = 0;
}
function is_fainting($buffer)
{
	global $fainting;
	//if fainting
	if(stripos($buffer, 'fainting'))
		$fainting = 1;//oh no time to pray
	else
		$fainting = 0;
}
function is_normal()
{
	global $normal,$hungry,$hurt,$weak,$fainting;

	if(!$hungry && !$hurt && !$weak && !$fainting)
		$normal = 1;
	else
		$normal = 0;
}
function GetHP($rowstr) 
{ 
    //returns a 3 element array of 
	//0: current HP int
	//1: max HP int
	//3: percentage HP float

	for( $i = 0; $i<25; $i++ ) 
    { 
        if( ($pos = stripos( $rowstr[$i], "HP" ) ) !== FALSE )
		{
			if( ($test = stripos( $rowstr[$i], "HP:Dlvl:1" ) ) === FALSE )  //vt102 bugfix the bottom line sometimes is parsed incorrectly
			{
				$row = $rowstr[$i];
				$pos = $pos + 3;
				while($row[$pos] != ")")
				{
					$r.=$row[$pos];
					$pos++;
				}
				$ret = explode("(",$r);
				$ret[2] = $ret[0]/$ret[1]*100;
				return $ret;//success
			}
			else 
				return 0;//buggy line was found
		}
	}
	return 0;//failure
}
function GetNHturn($rowstr) 
{ 

	for( $i = 22; $i<25; $i++ ) 
    { 
        if( ($pos = strpos( $rowstr[$i]," T" ) ) !== FALSE )
		{
			break; //found "HP"
		}
	}
	if($pos !== FALSE)
	{
		$row = $rowstr[$i];
		$pos = $pos + 3;
		while($row[$pos] != " ")
		{
			$r.=$row[$pos];
			$pos++;
		}
		return $r;//success
	}
	return 0;//failure
}
function GetCloserTo($myPosition, $itsPosition, $rowstr )
{
	$moveableChars = ".#><$!()[]{}?/*_=%abcdfghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ&@';:";//things that can be walked on(monsters included)
	//notice "e" is excluded...floating eyes are dangerous
	if( !isset($myPosition[0]) || !isset($itsPosition[0]) || !isset($rowstr[24]) )
	{
		return 0;
	}
	$noMoveSolution = 1;
	$vector = GetVector($myPosition, $itsPosition);
	$angle = $vector[1];
	$y = $myPosition[0];
	$x = $myPosition[1];
	$i=0;
	$logentry =  "angle:".$angle." myposition".$y." ".$x." itsposition:".$itsPosition[0]." ".$itsPosition[1]." ";

	while($noMoveSolution && $i<50)
	{
		$i++;

		if($angle > 337.5 || $angle < 22.5 )//0 degrees +/- 45 
		{
			$moveableChars.="\\";//add the open door because it can be walked over in this direction
			$movedirection = "6";
			$xdest = 1;//moving right
			$ydest = 0;//not moving up
		}
		else if( $angle >= 22.5 && $angle < 67.5 )
		{
			$moveableChars = stripslashes($moveableChars);//get rid of "\" (doors)
			$movedirection = "9";
			$xdest = 1;//moving right and up
			$ydest = -1;
		}
		else if( $angle >=67.5 && $angle < 112.5 )
		{
			$moveableChars.="\\";//add the open door because it can be walked over in this direction
			$movedirection = "8";
			$xdest = 0;
			$ydest = -1;//moving up
		}
		else if( $angle >= 112.5 && $angle < 157.5 )
		{
			$moveableChars = stripslashes($moveableChars);//get rid of "\" (doors)
			$movedirection = "7";
			$xdest = -1;
			$ydest = -1;//going left and up
		}
		else if( $angle >= 157.5 && $angle < 202.5 )
		{
			$moveableChars.="\\";//add the open door because it can be walked over in this direction
			$movedirection = "4";
			$xdest = -1;
			$ydest = 0;
		}
		else if( $angle >= 202.5 && $angle < 247.5 )
		{
			$moveableChars = stripslashes($moveableChars);//get rid of "\" (doors)
			$movedirection = "1";
			$xdest = -1;
			$ydest = 1;//down and left
		}
		else if( $angle >= 247.5 && $angle < 292.5 )
		{
			$moveableChars.="\\";//add the open door because it can be walked over in this direction
			$movedirection = "2";
			$xdest = 0;
			$ydest = 1;//down
		}
		else if( $angle >=292.5 && $angle < 337.5 )
		{
			$moveableChars = stripslashes($moveableChars);//get rid of "\" (doors)
			$movedirection = "3";
			$xdest = 1;
			$ydest = 1;
		}
		$destCharacter = $rowstr[$y+$ydest][$x+$xdest];
	
		if( strpos($moveableChars, $destCharacter) === FALSE)//direction to move in has something that cant be walked on
		{
			$angle += rand(-45, 45);
			if($angle<0)
				$angle = 360;
		}
		else
		{
			$noMoveSolution = 0;
		}
	}
	send($movedirection);
	return $movedirection;
}
function LocateClosestObject($myPosition, $rowstr, $object)
{

	$counter = 0;
	for( $i = 3; $i<22; $i++ ) 
    { 
        if( ($pos = stripos( $rowstr[$i],$object ) ) !== FALSE )
		{
			if( BoolComparePosition($myPosition, array( 0 => $i, 1 => $pos) ) === FALSE )//dont count self as closest object
			{
				$position[1][$counter] = $pos;//x
				//echo " ".$pos;
				$position[0][$counter] = $i;	//y
				//echo " ".$i;	
				$counter++;
			}
		}
	}

	if(!isset($position[0]) || !isset($position[1]) || !isset($myPosition[1]))//return 0 on finding no matching object
	{
		return 0;
	}
	$shortestdist = 1000;
	for($i = 0; $i<$counter; $i++)//look at the objects and see which is closest
	{
		$dist = sqrt (pow( ( $myPosition[0]-$position[0][$i] ),2 ) + pow( ($myPosition[1]-$position[1][$i] ),2));
		//echo " ".$dist;
		if($dist<$shortestdist)
		{
			$shortestdist = $dist;
			$num = $i;
		}
	}
	$location[0] = $position[0][$num];//y
	$location[1] = $position[1][$num];//x
	return $location;
}
function BoolComparePosition($position1, $position2)//returns true if the arguments are the same
{
	if(($position1[0] == $position2[0]) && ($position1[1] == $position2[1]))
		return TRUE;
	else
		return FALSE;
}
function GetVector($origin, $target)//returns a 2 element array the first being the distance
{									//the second being the angle in degrees
	$x = $target[1]-$origin[1];
	$y = -($target[0]-$origin[0]); 
	//the minus is because the y axis is inverted (nethack screen is drawn from top corner right and down)

	//get distance
	$dist = sqrt (pow( $x ,2 ) + pow( $y ,2 ));
	//get angle

	if($x == 0 || $y == 0)//this will result in one of four possible integer angles
	{
		if($x == 0 && $y >= 0)
		{
			$angle = 90;
		}
		else if($x == 0 && $y < 0)
		{
			$angle = 270;
		}
		else if($x >= 0 && $y == 0)
		{
			$angle = 0;
		}
		else if($x < 0 && $y == 0)
		{
			$angle = 180;
		}
	}
	else
	{
		$angle = atan ( $y / $x );//radians
		$angle = rad2deg($angle);
		
		if($x > 0 && $y < 0)
		{
			$angle+=360;	//compensate for reference angle return from the trig functions
		}
		else if($x < 0 && $y > 0)
		{
			$angle+=180;//compensate for reference angle return from the trig functions
		}
		else if($x < 0 && $y < 0)
		{
			$angle+=180;//compensate for reference angle return from the trig functions
		}
	
	}
	$vector[0] = $dist;
	$vector[1] = $angle;
	
	return $vector;
}
function explore()
{
	global $rowstr;
	global $myPosition;

	static $search= 0;
	static $exploretime = 0;
	static $direction = 1;

	if ($exploretime>20)
	{
		$direction = rand(1,4);
		$exploretime = 0;
	}
	if($rowstr[$myPosition[0]][$myPosition[1]+1] == "+")
	{
		send("o");//open door
		send("6");//right
	}
	if($rowstr[$myPosition[0]][$myPosition[1]-1] == "+")
	{
		send("o");//open door
		send("4");//left
	}
	if($rowstr[$myPosition[0]+1][$myPosition[1]] == "+")
	{
		send("o");//open door
		send("2");//down
	}
	if($rowstr[$myPosition[0]-1][$myPosition[1]] == "+")
	{
		send("o");//open door
		send("8");//up
	}
	
	switch($direction)
	{
		case 1: //west
			$dest[0] = $myPosition[0];
			$dest[1] = $myPosition[1]+1;
			GetCloserTo($myPosition, $dest, $rowstr );
		break;
		case 2: //east
			$dest[0] = $myPosition[0];
			$dest[1] = $myPosition[1]-1;
			GetCloserTo($myPosition, $dest, $rowstr );
		break;
		case 3: //north
			$dest[0] = $myPosition[0]-1;
			$dest[1] = $myPosition[1];
			GetCloserTo($myPosition, $dest, $rowstr );
		break;
		case 4: //south
			$dest[0] = $myPosition[0]+1;
			$dest[1] = $myPosition[1];
			GetCloserTo($myPosition, $dest, $rowstr );
		break;
		default: //east
			$dest[0] = $myPosition[0];
			$dest[1] = $myPosition[1]-1;
			GetCloserTo($myPosition, $dest, $rowstr );
		break;
	}

	$exploretime++;
	$search++;
	if($search>=8)
	{
		send("s");//search
		$search = 0;
	}
	return $direction;
}
function logCommand($command)
{
	$commandfile = fopen("c:/rascal/command_file.txt", "a");//log file of the actions and results of this script
	fwrite($commandfile, $command."\n");
	fclose($commandfile);
}
function send($key)
{
	global $fp;
	fwrite($fp, $key);
	usleep(100000);//viewing delay
}
function find($type)
{
	global $myPosition;
	global $rowstr;
	
	$i = 0;
	switch ($type)
	{
		case 0: //downstairs
			$o = ">";
		break;
		case 1: //treasure
			$o = "/?*$!()=";
		break;
		case 2: //monsters
			$o = "abcdfghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ&';:";
		break;
		default:
			return 0;
		break;
	}

	while($i<=strlen($o))
	{
		$objective = LocateClosestObject($myPosition, $rowstr, $o[$i]);
		if(isset($objective[0]))
		{
			return $objective;
		}
		$i++;
	}
	return 0;
}

?> 