<?php /*
 * Version 0.1 Enrico Pasqualotto epasqualotto@backloop.biz
 *
 **/ // select calldate,src,dst,duration,uniqueid,recordingfile from cdr WHERE src='403' OR dst='403' ORDER BY calldate DESC LIMIT 0,10;
//error_reporting(E_ALL);
include("phpagi.php");
#ip address that asterisk is on.
$ami_host = "127.0.0.1";#specify the username you want to login with
$ami_user = "admin";#specify the password for the above user
$ami_pass = "zxcvb";
$exten_prefix = "PJSIP";
$rec_path = "https://x.y.z/api/recordings/";
$servername = "localhost";
$username = "freepbxuser";
$password = "zxcvb";
$db_name = "asteriskcdrdb";
$dest_context = "from-internal";
$channel_info = "";

// DB connection
// Create connection
$conn = new mysqli($servername, $username, $password,$db_name); // Check connection
if ($conn->connect_error) {
  //die("Connection failed: " . $conn->connect_error);
        $out['result'] = "false";
        $out['message'] = "Connection failed: " . $conn->connect_error;
}
//echo "Connected successfully";

if ($_REQUEST['method'] == "c2c"){
        if (($_REQUEST['exten'] != "")&&($_REQUEST['destination'] != ""))
                $out = c2c($_REQUEST['exten'],$_REQUEST['destination']);
        else
                $out = array("result" => "false","message" => "Mandatory field exten & destination not found.");
} else if ($_REQUEST['method'] == "getRecCall"){
        if ($_REQUEST['exten'] != "")
                $out = get_call($_REQUEST['exten'],$_REQUEST['uniqueid'],"both","1");
        else
                $out = array("result" => "false","message" => "Mandatory field exten not found.");
} else if ($_REQUEST['method'] == "getCalls"){
        $out = get_call($_REQUEST['exten'],"","both","100");
}
$json_out = json_encode($out);
header('Content-Type: application/json');
echo $json_out;
$conn->close();

function get_call($exten,$uniqueid="",$direction="both",$num_rec="1"){

        global $conn;
        global $rec_path;


        if ($uniqueid == ""){
                if (($direction == "both")||($direction == ""))
                        $sql = "select calldate,src,dst,duration,uniqueid,recordingfile from cdr WHERE src='".$exten."' OR dst='".$exten."' OR cnum='".$exten."' ORDER BY calldate DESC LIMIT 0,".$num_rec;
                else if ($direction == "in")
                        $sql = "select calldate,src,dst,duration,uniqueid,recordingfile from cdr WHERE dst='".$exten."' ORDER BY calldate DESC LIMIT 0,".$num_rec;
                else if ($direction == "out")
                        $sql = "select calldate,src,dst,duration,uniqueid,recordingfile from cdr WHERE src='".$exten."' OR cnum='".$exten."' ORDER BY calldate DESC LIMIT 0,".$num_rec;
        } else {
                $sql = "select calldate,src,dst,duration,uniqueid,recordingfile from cdr WHERE uniqueid='".$uniqueid."' ORDER BY calldate DESC LIMIT 0,".$num_rec;
        }

        $result = $conn->query($sql);
        if ($result->num_rows == 1) {
                $ret['result'] = "true";

                // output data of each row
                $row = $result->fetch_assoc();

                $tmp_timestamp = explode(" ",$row['calldate']);
                $tmp_date = explode("-",$tmp_timestamp['0']);
                if ($row['recordingfile'] != "")
                        $rec_call_path = $rec_path.$tmp_date['0']."/".$tmp_date['1']."/".$tmp_date['2']."/".$row['recordingfile'];
                else
                        $rec_call_path = "n/a";

                $ret['exten'] = $row["src"];
                $ret['destination'] = $row["dst"];
                $ret['duration'] = $row["duration"];
                $ret['rec_location'] = $rec_call_path;
                $ret['uniqueid'] = $row["uniqueid"];
                $ret['linkedid'] = $row["linkedid"];
                $ret['calldate'] = $row["calldate"];


        } else if ($result->num_rows > 1) {

                $ret['result'] = "true";
                $ret['exten'] = $exten;
                $calls = array();
                while($row = $result->fetch_assoc()) {

                        $tmp_timestamp = explode(" ",$row['calldate']);
                        $tmp_date = explode("-",$tmp_timestamp['0']);
                        if ($row['recordingfile'] != "")
                                $rec_call_path = $rec_path.$tmp_date['0']."/".$tmp_date['1']."/".$tmp_date['2']."/".$row['recordingfile'];
                        else
                                $rec_call_path = "n/a";

                        $call['exten'] = $row["src"];
                        $call['destination'] = $row["dst"];
                        $call['duration'] = $row["duration"];
                        $call['rec_location'] = $rec_call_path;
                        $call['uniqueid'] = $row["uniqueid"];
                        $call['linkedid'] = $row["linkedid"];
                        $call['calldate'] = $row["calldate"];

                        $calls[] = $call;
                }
                $ret['calls'] = $calls;
        } else {
                $ret['result'] = "false";
                $ret['message'] = $conn->error." (".$sql.")";
        }
        return $ret;
}
function c2c_old($src,$number){
        global $exten_prefix;
        global $ami_host;
        global $ami_user;
        global $ami_pass;
        global $dest_context;

        #specify the amount of time you want to try calling the specified channel before hangin up
        $strWaitTime = "30";
        #specify the priority you wish to place on making this call
        $strPriority = "1";
        #specify the maximum amount of retries
        $strMaxRetry = "2";
        $strCallerId = "Web Call $n";
        $strChannel = $exten_prefix."/".$src;

        $oSocket = fsockopen($ami_host, 5038, $errno, $errstr, 20);
        if (!$oSocket) {
                $ret['result'] = "true";
                $ret['message'] = $errstr." (".$errno.")";

        } else {
                fputs($oSocket, "Action: login\r\n");
                fputs($oSocket, "Events: off\r\n");
                fputs($oSocket, "Username: $ami_user\r\n");
                fputs($oSocket, "Secret: $ami_pass\r\n\r\n");
                fputs($oSocket, "Action: originate\r\n");
                fputs($oSocket, "Channel: $strChannel\r\n");
                fputs($oSocket, "WaitTime: $strWaitTime\r\n");
                fputs($oSocket, "CallerId: $strCallerId\r\n");
                fputs($oSocket, "Exten: $number\r\n");
                fputs($oSocket, "Context: $dest_context\r\n");
                fputs($oSocket, "Priority: $strPriority\r\n\r\n");
                //fputs($oSocket, "Async: yes\r\n\r\n");


                $wrets = "";
                for($i=0;$i<5;$i++){
                        $tmp = fgets($oSocket,128);
                        if ($tmp == "")
                                break;
                        else
                                $wrets.=$tmp;
                }
                sleep(2);
                fputs($oSocket, "Action: Logoff\r\n\r\n");
                fclose($oSocket);

                $out = get_call($src,"","","2");
                $ret['result'] = "true";
                $ret['message'] = "Extension $strChannel should be calling $number.".print_r($out,1)." out:".$wrets;
        }


        return $ret;

}
function newchannel($event, $parameters){
        global $channel_info;
        //$ret['1'] = $event;
        $channel_info = $parameters;
        //print_r($ret);
        //die();
        //return $ret;
}
function c2c($src,$number){
        global $exten_prefix;
        global $ami_host;
        global $ami_user;
        global $ami_pass;
        global $dest_context;
        global $channel_info;

        #specify the amount of time you want to try calling the specified channel before hangin up
        $strWaitTime = "30";
        #specify the priority you wish to place on making this call
        $strPriority = "1";
        #specify the maximum amount of retries
        $strMaxRetry = "2";
        $strCallerId = $number;
        $strChannel = $exten_prefix."/".$src;

        $oSocket = fsockopen($ami_host, 5038, $errno, $errstr, 20);
        if (!$oSocket) {
                $ret['result'] = "true";
                $ret['message'] = $errstr." (".$errno.")";

        } else {
                $agi = new AGI_AsteriskManager();
                $agi->connect($ami_host,$ami_user,$ami_pass);

                $out= $agi->Originate($strChannel,$number, $dest_context, 1,"","","", $strCallerId,"", "","", "");
                $agi->add_event_handler('Newchannel', 'newchannel');
                $out2 = $agi->Events("on");

                for($i=0;$i<20;$i++){
                        if ($channel_info != ""){
                                $info = $channel_info;
                                break;
                        } else {
                                sleep(1);
                        }
                }
                //while($channel_info != "")
                //      $tmp3 = $channel_info;
                $ret['message'] = "Extension $strChannel should be calling $number.";
                $ret['uniqueid'] = $info['Uniqueid'];
                /*
                fputs($oSocket, "Action: login\r\n");
                fputs($oSocket, "Events: off\r\n");
                fputs($oSocket, "Username: $ami_user\r\n");
                fputs($oSocket, "Secret: $ami_pass\r\n\r\n");
                fputs($oSocket, "Action: originate\r\n");
                fputs($oSocket, "Channel: $strChannel\r\n");
                fputs($oSocket, "WaitTime: $strWaitTime\r\n");
                fputs($oSocket, "CallerId: $strCallerId\r\n");
                fputs($oSocket, "Exten: $number\r\n");
                fputs($oSocket, "Context: $dest_context\r\n");
                fputs($oSocket, "Priority: $strPriority\r\n\r\n");
                //fputs($oSocket, "Async: yes\r\n\r\n");


                $wrets = "";
                for($i=0;$i<5;$i++){
                        $tmp = fgets($oSocket,128);
                        if ($tmp == "")
                                break;
                        else
                                $wrets.=$tmp;
                }
                sleep(2);
                fputs($oSocket, "Action: Logoff\r\n\r\n");
                fclose($oSocket);

                $out = get_call($src,"","","2");
                $ret['result'] = "true";
                $ret['message'] = "Extension $strChannel should be calling $number.".print_r($out,1)." out:".$wrets;
                */
        }


        return $ret;

}



== Click2Call

https://admin:RglG0QOU8t7G@bncpxb.bncnetwork.net/api/?method=c2c&exten=90225&destination=0554400191

output example:

{"message":"Extension PJSIP\/90225 should be calling 0554400191.","uniqueid":"1628700204.23177"}

== Fetch call by uniqueid

https://admin:RglG0QOU8t7G@bncpxb.bncnetwork.net/api/?method=getRecCall&uniqueid=1628702175.23182&exten=225

output example:

{"result":"true","exten":"0554400191","destination":"0554400191","duration":"10","rec_location":"n\/a","uniqueid":"1628702175.23182","linkedid":null,"calldate":"2021-08-11 21:16:26"}

== Fetch calls by exten

https://admin:RglG0QOU8t7G@bncpxb.bncnetwork.net/api/?method=getCalls&exten=225

Output example

{"result":"true","exten":"225","calls":[{"exten":"065031395","destination":"0554400191","duration":"30","rec_location":"https:\/\/bncpxb.bncnetwork.net\/api\/recordings\/2021\/08\/11\/out-0554400191-225-20210811-204339-1628700204.23177.wav","uniqueid":"1628700204.23177","linkedid":null,"calldate":"2021-08-11 20:43:24"},{"exten":"065031395","destination":"0554400191","duration":"19","rec_location":"n\/a","uniqueid":"1628699904.23175","linkedid":null,"calldate":"2021-08-11 20:38:24"},{"exten":"0554400191","destination":"225","duration":"8","rec_location":"n\/a","uniqueid":"1628485377.396","linkedid":null,"calldate":"2021-08-09 09:02:57"},{"exten":"065031395","destination":"+971554400191","duration":"0","rec_location":"n\/a","uniqueid":"1628483348.102","linkedid":null,"calldate":"2021-08-09 08:29:08"},{"exten":"065031395","destination":"+971554400191","duration":"0","rec_location":"n\/a","uniqueid":"1628483312.90","linkedid":null,"calldate":"2021-08-09 08:28:32"},{"exten":"065031395","destination":"0554400191","duration":"4","rec_location":"n\/a","uniqueid":"1628483184.82","linkedid":null,"calldate":"2021-08-09 08:26:24"},{"exten":"065031225","destination":"0554400191","duration":"9","rec_location":"n\/a","uniqueid":"1628483045.62","linkedid":null,"calldate":"2021-08-09 08:24:05"}]}
