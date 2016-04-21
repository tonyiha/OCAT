<?php
error_reporting(E_ALL & ~E_NOTICE);
session_start();
include_once('includes/queryfunctions.php');
include_once('includes/classes.php');
include_once('includes/functions.php');
$conn = mysqli_connect(HOST, USER, PASS, DB);
if (!$conn) {
    die('Could not connect: ' . mysqli_error());
}

SignedIn(); //check if user is logged in
$_SESSION['currentFile'] = GetCurrentFile();

//check if user has clicked on logout button
if(isset($_POST["submit"]) && $_POST["submit"]=='Logout') LogOut();

//have a function to count the number of capacity areas
empty($_GET['capacityarea']) ? $_GET['capacityarea']=1 : $_SESSION['capacityarea'] = $_GET['capacityarea']+1;

if(isset($_GET["search"]) && !empty($_GET["search"])){
	//have this as a search function
	$search=$_GET["search"];
	$_POST["id"] = $_GET["search"];
	$_POST["submit"]="Find";
}

if(isset($_POST["submit"])){
	if($_POST["submit"]=="Add" || $_POST["submit"]=="Edit"){
		$id =(!empty($_POST["id"])) ? $_POST["id"] : 'NULL';
		$fk_assessment_id = !empty($_POST["fk_assessment_id"]) ? $_POST["fk_assessment_id"] : 'NULL';
		$fk_participant_id = !empty($_POST["fk_participant_id"]) ? $_POST["fk_participant_id"] : 'NULL';
		$participant_cat = !empty($_POST["participant_cat"]) ? $_POST["participant_cat"] : 'NULL';
		$addedby = $_SESSION['userid'];
	}
	
	switch($_POST["submit"]){
	case "Add":
		//loop through the scored capacity areas, if empty skip
		$ca_count = GetValue("SELECT COUNT(*) FROM od_capacity_areas");

		$capacityarea = $_POST['capacityarea'];
		$sql = "SELECT cad.id,fk_ca_subcat_id,capacity_area_detail,cad.level
				FROM od_ca_details AS cad
				LEFT JOIN od_ca_subcategory AS cas ON cad.fk_ca_subcat_id = cas.id
				LEFT JOIN od_capacity_areas AS ca ON cas.fk_capacity_area_id = ca.id
				WHERE ca.id = $capacityarea";
		$ca_results = query($sql,$conn);	
		$j = 0;
		$i = 0;
		while ($row = mysqli_fetch_object($ca_results)) {
			if(empty($_POST["score$row->fk_ca_subcat_id"])) continue; //skip on capacity area detail where scores have not been entered
			$i++;
			$score_cadetail = !empty($_POST["score$row->fk_ca_subcat_id"]) ? explode(":",$_POST["score$row->fk_ca_subcat_id"]) : 'NULL';
			$fk_ca_detail_id = $score_cadetail[0];
			$score = $score_cadetail[1];
			$remarks = !empty($_POST["remarks$row->id"]) ? $_POST["remarks$row->id"] : 'NULL';	//sanitize data
			$remarks = ($remarks=='NULL') ? 'NULL' : "'" . mysqli_real_escape_string($conn,$remarks) . "'"; //if null ? null : sanitize

			$sql="INSERT INTO assessment_score (fk_assessment_id,participant_cat,fk_ca_detail_id,fk_participant_id,score,remarks,addedby,dateadded)
				VALUES($fk_assessment_id,$participant_cat,$fk_ca_detail_id,$fk_participant_id,$score,$remarks,$addedby,now())";

			$results=query($sql,$conn);
			if ((int) $results==0){
				$j++;
				//echo "Error for: " . $row->capacity_area_detail . "<br>";
			}	
			//$msg[0]="Sorry assessment score not added";
			//$msg[1]="Assessment score successfully added";
			//AddSuccess($results,$conn,$msg);	//should capture the one that was not saved and give an error for that only and one for all the captured data.
		}
		$k = $i-$j;
		echo "<div class='success' align='center'>$k of $i scores have been added.</div>";
		
		$_POST["id"] = $_POST['fk_cso_id'];
		$_GET['assessmentid'] = $_POST['fk_assessment_id'];		//+1 will move it automatically to the next capacity area - way to go.
		$_GET['capacityarea'] = $_POST['capacityarea'];
		$_GET['searchtab'] = 'organization';
		$_GET['search'] = $_POST['fk_cso_id'];;
		
		SearchAssessment();
		break;	
	case "Edit":
		$id=$_POST["id"];
		$sql="UPDATE assessment_score SET fk_assessment_id=$fk_assessment_id,fk_ca_detail_id=$fk_ca_detail_id,fk_participant_id=$fk_participant_id,
				score=$score,remarks=$remarks
			WHERE id=$id";
		$results=query($sql,$conn);
		$msg[0]="Sorry assessment score not updated";
		$msg[1]="Assessment score successfully updated";
		AddSuccess($results,$conn,$msg);
		break;	
	case "Delete":
		$id=$_POST["id"];
		$sql = "DELETE FROM assessment_score WHERE id=$id";
		$results=query($sql,$conn);
		$msg[0]="Sorry assessment score not deleted";
		$msg[1]="Assessment score successfully deleted";
		AddSuccess($results,$conn,$msg);		
		break;
	case "Find":
		SearchAssessment();
		break;
	}
}

//get Participants and No. of participants for the assessment, to determine column span -	 || !empty($_POST['assessmentid'])
$colspan = "";
if(!empty($_GET['assessmentid']) || !empty($_POST['assessmentid'])){
	$sql = "SELECT fk_assessment_id,fk_participant_id FROM assessment_score WHERE fk_assessment_id=$_GET[assessmentid] GROUP BY fk_participant_id";
	$results = query($sql,$conn);
	$i = 1;
	while($row = fetch_object($results)){
		$participants[$i]=$row->fk_participant_id;
		$i++;
	}
	$colspan = num_rows(query($sql,$conn));
}	

function SearchAssessment(){
	global $conn,$assessment,$rowsfound;
	$id=$_POST["id"];
	$assessmentid = $_GET['assessmentid'];
	//have a different sql query when adding, since the search will be on a grantee not on issue - todo before next review
	//search on grants file
	if($_GET['searchtab']=='organization'){
		//Searches for an assessment on a register to add a new score
		$sql = "SELECT SQL_BUFFER_RESULT SQL_CALC_FOUND_ROWS a.id,a.fk_cso_id,c.organization_name,c.short_name,a.fk_status_id,a.assessment_date,a.dateadded
					FROM assessment AS a
					LEFT JOIN cso AS c ON a.fk_cso_id = c.id WHERE c.id=$id AND a.id=$assessmentid";
		unset($_GET["search"]);
	}else{
		//TODO - make this to search for an assessment scores
		$id = $_GET['assessmentid'];
		$sql = "SELECT SQL_BUFFER_RESULT SQL_CALC_FOUND_ROWS g.*,a.*,u.`user`,u.loginname
				FROM assessment AS a
				LEFT JOIN cso AS g ON g.id = a.fk_cso_id
				LEFT JOIN users AS u ON u.userid = a.addedby
				WHERE a.id = $id";
		//unset($_GET["search"]);		
	}
	$results=query($sql,$conn);
	$rowsfound = num_rows($results);
	$assessment = fetch_object($results);
}
?>
<!doctype html public "-//W3C//DTD HTML 4.0 //EN"> 
<html>
<head>
<title>VIWANGO - Capacity Area Scoring</title>
<link rel="stylesheet" type="text/css" href="css/main.css"/>
<link rel="stylesheet" type="text/css" href="css/epoch_styles.css"/>
<script language="JavaScript" src="js/highlight.js" type="text/javascript"></script>
<script type="text/javascript" src="js/formval.js"></script>
<script type="text/javascript" src="css/epoch_classes.js"></script>
<script type="text/javascript">
<!--
var request;
var dest;
var dp_cal;

window.onload = function () {
	dp_cal  = new Epoch('epoch_popup','popup',document.getElementById('assessment_date'));
};

function validateOnSubmit() {
	var elem;
    var errs=0;
	// execute all element validations in reverse order, so focus gets
    // set to the first one in error.
	
	if (!validateNum (document.forms.assessmentscore.fk_participant_id,'inf_fk_participant_id',1)) errs += 1;

    if (errs>1)  alert('There are fields which need correction before sending');
    if (errs==1) alert('There is a field which needs correction before sending');

    return (errs==0);
};

function processStateChange(){
    if (request.readyState == 4){
        contentDiv = document.getElementById(dest);
        if (request.status == 200){
            response = request.responseText;
            contentDiv.innerHTML = response;
        } else {
            contentDiv.innerHTML = "Error: Status "+request.status;
        }
    }
}

function loadHTMLPost(URL, destination, button){
    dest = destination;
	org_id = document.getElementById('searched').value;
	var str ='org_id='+org_id+'&button='+button;
	if (window.XMLHttpRequest){
        request = new XMLHttpRequest();
        request.onreadystatechange = processStateChange;
        request.open("POST", URL, true);
        request.setRequestHeader("Content-Type","application/x-www-form-urlencoded; charset=UTF-8");
		request.send(str);
    } else if (window.ActiveXObject) {
        request = new ActiveXObject("Microsoft.XMLHTTP");
        if (request) {
            request.onreadystatechange = processStateChange;
            request.open("POST", URL, true);
            request.send();
        }
    }
}
//-->	 
</script>
</head>
<body>
<form method="post" action='assessmentscore.php'  id="assessmentscore" enctype="multipart/form-data" name="assessmentscore">
<div align="center">
    <table width="95%" class="main">
		<?php topheader(); ?>
		<tr> 
        <td colspan="2"><a href="assessment_register.php">Assessment List</a> | <a href="#" title="convert to word"> 
          <img src="images/doc.gif" width="16" height="16" border="0"></a></td>
        <td colspan="2" align="right">
          <?php login_status(); ?>
        </td>
      </tr>
      <tr>
	  <td valign="top" width="204"><div style="width: 200px;"><?php implementationmenu(); ?></div></td>
     <td colspan="3" valign="top">
	 <table border="1" width="100%">
            <tr> 
              <td colspan="<?php echo !empty($colspan) ? $colspan+4 : 4; ?>"> 
                CSO: - Search by typing any word of the grantees name in the 
                text box and press tab.: 
                <input type="text" name="searched" id="searched" size="55" onBlur="loadHTMLPost('ajaxfunctions.php','grantees_list','organization')"> 
                <div id="grantees_list"></div></td>
            </tr>
            <tr> 
              <th colspan="3"> <h2> 
                  <?php 
				if(empty($assessment->id)){
					echo "CSO Assessments";
				}else{
			  		echo "Assessments for: <a href='organization.php?search=$assessment->fk_organization_id'>$assessment->organization_name ($assessment->short_name)</a>";
				}
			   ?>
                </h2></th>
              <th colspan="<?php echo (empty($colspan)) ? 1 : $colspan+1; ?>"><h3>CSO 
                  ID.: <?php echo $assessment->id; ?></h3></th>
            </tr>
            <tr> 
              <th colspan="<?php echo !empty($colspan) ? $colspan+1 : 1; ?>">Assessment 
                Details 
                <input type="hidden" name="fk_assessment_id" value="<?php echo !empty($assessment->id) ? $assessment->id : $_GET['assessmentid']; ?>"> 
                <input type="hidden" name="fk_cso_id" value="<?php echo $assessment->fk_cso_id; ?>"> 
                <input type="hidden" name="capacityarea" value="<?php echo $_GET['capacityarea']; ?>">
                </th>
               <td colspan="3"><?php oca($assessment->id); ?></td> 
            </tr>
            <tr> 
              <td>
              Participant Category <label><input type="radio" name="participant_cat" value=1 />Group</label>
              <label><input type="radio" name="participant_cat" value=2 />Individual</label>
              </td>
              <td colspan="<?php echo !empty($colspan) ? $colspan+3 : 3; ?>">
               <strong>Participant ID.</strong>
              <input type="text" name="fk_participant_id" id="fk_participant_id" size="5"> 
              <div id="inf_fk_participant_id" class="warn">* </div>
              </td>
            </tr>
            <?php
			//loop for capacity area
			$sql = "SELECT id,refno,capacity_area,remarks FROM od_capacity_areas WHERE id=$_GET[capacityarea]";	//start with governance, move to management etc in a survey sot off
			$ca_results = query($sql,$conn);
			while($ca_data = fetch_object($ca_results)){
			?>
            <tr> 
              <td bgcolor="ffc000" colspan="<?php echo !empty($colspan) ? $colspan+4 : 4; ?>"><h1 align="center"><?php echo $ca_data->refno.': '.$ca_data->capacity_area; ?><br>
                  <?php
			  //get the capacity areas links 1|2|3...	
				$sql = "SELECT id,refno,capacity_area FROM od_capacity_areas";
				$results = query($sql,$conn);
				while($row = fetch_object($results)){
					echo "<a href=\"assessmentscore.php?search=$assessment->fk_cso_id&searchtab=organization&capacityarea=$row->id&assessmentid=$_GET[assessmentid]\" title='$row->capacity_area'>" . $row->id . "</a>" . " |&nbsp;";
				}
			  ?>
                </h1>
                </td>
            </tr>
			
            <tr> 
              <td colspan="2" align="right">Participants</td>
			  <th>Rating</th>
			  <?php
				if(is_array($participants)){
				foreach($participants as &$value){
					echo "<td>$value</td>";
				}
				unset($value);
				}
			  ?>
			  <th>Average</th>			  
            </tr>
			
            <?php
				//loop for subcategory capacity area
				$sql = "SELECT id,refno,capacity_area_subcategory,remarks FROM od_ca_subcategory WHERE fk_capacity_area_id=$ca_data->id";
				$casubcat_results = query($sql,$conn);
				while($casubcat_data = fetch_object($casubcat_results)){
				?>
            <tr> 
    	        <th colspan="<?php echo !empty($colspan) ? $colspan+3 : 4; ?>"><strong><?php echo $casubcat_data->refno.' '.$casubcat_data->capacity_area_subcategory; ?></strong></th>
	           	<td><img src="images/icon_assistant.gif" title="guide" onmouseover="showstandard(<?php echo $casubcat_data->id; ?>)"/></td>
            </tr>
            <?php
				//loop for capacity area details for capturing scores on input box
				$sql = "SELECT id,fk_ca_subcat_id,capacity_area_detail,remarks,level FROM od_ca_details WHERE fk_ca_subcat_id=$casubcat_data->id ORDER BY level";
				$cadetail_results = query($sql,$conn);
				echo "<tr class='capacityarea'><td colspan=\""; echo !empty($colspan) ? $colspan+4 : 4; echo "\"><span style=\"font-weight: bold\">Standard: $casubcat_data->remarks</span></td></tr>";
				while($cadetail_results_data = fetch_object($cadetail_results)){
			?>
            <tr bgcolor="d4d4d0"> 
              <td colspan="2"><?php echo $cadetail_results_data->capacity_area_detail; ?> 
                <input type="hidden" name="fk_ca_detail_id<?php echo $cadetail_results_data->id; ?>" value="<?php echo $cadetail_results_data->id; ?>"> 
              </td>
              <td><input name="score<?php echo $cadetail_results_data->fk_ca_subcat_id; ?>" value="<?php echo $cadetail_results_data->id.':'.$cadetail_results_data->level; ?>" type="radio" size="5"><?php echo $cadetail_results_data->level; ?></td>
              <?php
			  //loop through participants on this capacity area detail for this particular assessment		
			  $assessmentid = $_GET['assessmentid'];
			  //$scored_ca = num_rows($score_results);	//get total participants
			  $sql_participants = "SELECT assessment_score.fk_participant_id
				FROM assessment_score
				WHERE assessment_score.fk_assessment_id = $assessmentid
				GROUP BY assessment_score.fk_participant_id";
				$scored_ca = num_rows(query($sql_participants,$conn));

				//while($participant_score = fetch_object($score_results)){
					for($i=1;$i<=$scored_ca;$i++){
						$sql2 = "SELECT fk_participant_id,score FROM v_assessmentscore WHERE fk_assessment_id=$assessmentid AND fk_ca_detail_id = $cadetail_results_data->id AND fk_participant_id=$i";
			  			$score_results = query($sql2,$conn);
						$participant_score = fetch_object($score_results);
						if($score_results){
							echo "<td>".$participant_score->score."</td>";
						}else{
							echo "<td>&nbsp</td>";
						}
					}
				//}	
			  ?>
            </tr>
            <?php } //end loop for capacity area details ?>
            	<tr>
            		<td colspan="3">Remarks: <input type="text" name="remarks<?php echo $casubcat_data->id; ?>" size="100"/></td>
            		<?php
						$assessmentid = $_GET['assessmentid'];
						$sql5 = "SELECT fk_participant_id,score FROM v_assessmentscore WHERE fk_assessment_id=$assessmentid AND fk_ca_subcat_id = $casubcat_data->id ORDER BY fk_ca_subcat_id, fk_participant_id";
						$participant_scores = query($sql5,$conn);
						while($row = fetch_object($participant_scores)){
							echo "<td>".$row->score."</td>";							
						}
            		?>
            	</tr>
			<?php 
				}//end for loop for subcategory capacity area
			}//end for capacity area
			?>
            <tr> 
              <td colspan="2">&nbsp;</td>
			  <td>
                <?php if($_SESSION["privledge"]>1){ ?>
                <input type="submit" name="submit" value="<?php echo isset($_GET["search"]) ? "Edit" : "Add"; ?>" onClick="return validateOnSubmit();"> 
                <?php }; ?>
                <input type="hidden" name="id" value="<?php echo $award->id; ?>"> 
                </td>
				<td colspan="<?php echo $colspan+1; ?>">&nbsp;</td>
            </tr>
          </table>
        </td>
      </tr>
      <tr> 
        <td colspan="4"><div align="center">Manuals | FAQ | Issues</div></td>
      </tr>
    </table>
</div>
</form>
</body>
<script language=Javascript>
   function showstandard(subcat_id)
	 {
        var url = "showguide.php?guide="+subcat_id;
   
        newwin = window.open(url,'Add',"width=400,height=350,toolbar=0,location=0,directories=0,status=0,menuBar=0,scrollbars=3");
        newwin.focus();
     }
</script>
</html>