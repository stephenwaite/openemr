function togglePanel(divid,imgid1,imgid2,barid)
{
  var numargs=arguments.length;
  if(document.getElementById(divid).style.display == 'none') {
    document.getElementById(divid).style.display = 'block';
    document.getElementById(imgid1).src = '../../../library/wmt/fill-090.png';
    document.getElementById(imgid2).src = '../../../library/wmt/fill-090.png';
    document.getElementById(barid).style.borderBottom = 'solid 1px black';
  } else {
    document.getElementById(divid).style.display = 'none';
    document.getElementById(imgid1).src = '../../../library/wmt/fill-270.png';
    document.getElementById(imgid2).src = '../../../library/wmt/fill-270.png';
    document.getElementById(barid).style.borderBottom = 'none';
    if(barid == 'GEAllergyCollapseBar') {
      document.getElementById(barid).style.borderBottom = 'solid 1px black';
    }
    if(barid == 'GEMedsCollapseBar') {
      document.getElementById(barid).style.borderBottom = 'solid 1px black';
    }
    if(barid == 'DBAllergyCollapseBar') {
      document.getElementById(barid).style.borderBottom = 'solid 1px black';
    }
    if(barid == 'DBMedsCollapseBar') {
      document.getElementById(barid).style.borderBottom = 'solid 1px black';
    }
  }
  // This sets the bottom border of the bar for special boxes
  if(numargs >= 5) {
    if(arguments[4] == 'line') {
      document.getElementById(barid).style.borderBottom = 'solid 1px black';
    }
  }
  if(numargs >= 6) {
		var mode_id=arguments[5];
		// alert("Mode Element: "+mode_id);
    document.getElementById(mode_id).value = 
														document.getElementById(divid).style.display;
		// alert("Element Value: "+document.getElementById(mode_id).value);
  }
}

function TogglePair(ChkBox, UnBox)
{
  if(document.getElementById(ChkBox).checked == true) {
    document.getElementById(UnBox).checked = false;
  }
}

function VerifyYesChecks(YesBox, NoBox)
{
  if(document.getElementById(YesBox).checked == true) {
    document.getElementById(NoBox).checked = false;
  }
}

function VerifyNoChecks(YesBox, NoBox)
{
  if(document.getElementById(NoBox).checked == true) {
    document.getElementById(YesBox).checked = false;
  }
}

function VerifyYesFirstCheck()
{
  var numargs=arguments.length;
  // The First Item is the Yes Box
  if(document.getElementById(arguments[0]).checked == false) return(1);
  for (var i = 1; i < numargs; i++) {
     document.getElementById(arguments[i]).checked = false;
  }
}

function UpdateBMI(height, weight, bmi, bmi_status)
{
  var tmp_bmi = '';
  var tmp_bmi_status = '';

  var ht = document.getElementById(height).value;
  ht = Math.round(ht * 100) / 100;
  ht = ht.toFixed(2);
  document.getElementById(height).value = ht;

  var wt = document.getElementById(weight).value;
  wt = Math.round(wt * 100) / 100;
  wt = wt.toFixed(2);
  document.getElementById(weight).value = wt;

  if((wt <= 0) || (ht <= 0)) {
    document.getElementById(bmi).value = '';
    document.getElementById(bmi_status).value = '';
    return false;
  }

  tmp_bmi = ((wt/ht/ht) * 703);
  tmp_bmi = Math.round(tmp_bmi * 10) / 10;
  tmp_bmi = tmp_bmi.toFixed(1);
  if(tmp_bmi > 42) {
    tmp_bmi_status = 'Obesity III';
  } else if (tmp_bmi > 34) {
    tmp_bmi_status = 'Obesity II';
  } else if (tmp_bmi > 30) {
    tmp_bmi_status = 'Obesity I';
  } else if (tmp_bmi > 27) {
    tmp_bmi_status = 'Overweight';
  } else if (tmp_bmi > 25) {
    tmp_bmi_status = 'Normal BL';
  } else if (tmp_bmi > 18.5) {
    tmp_bmi_status = 'Normal';
  } else if (tmp_bmi) {
    tmp_bmi_status = 'Underweight';
  }
  if( tmp_bmi ) {
    document.getElementById(bmi).value = tmp_bmi;
    document.getElementById(bmi_status).value = tmp_bmi_status;
  }
}

function NoDecimal(thisField)
{
  var tmp = document.getElementById(thisField).value;
  tmp = Math.round(tmp);
  if(tmp == '0') tmp='';
  document.getElementById(thisField).value = tmp;
}

function OneDecimal(thisField)
{
  var tmp = document.getElementById(thisField).value;
  tmp = Math.round(tmp * 10) / 10;
  tmp = tmp.toFixed(1);
  if(tmp == '0.0') tmp='';
  document.getElementById(thisField).value = tmp;
}

function TwoDecimal(thisField)
{
  var tmp = document.getElementById(thisField).value;
  tmp = Math.round(tmp * 100) / 100;
  tmp = tmp.toFixed(2);
  if(tmp == '0.00') tmp='';
  document.getElementById(thisField).value = tmp;
}

function CalcPatAge(thisDate, thisAge)
{
  var dob = document.getElementById(thisDate).value;
  var age = document.getElementById(thisAge).value;
  if(age > 0) {
    return true;
  }
  dob = new Date(dob);  
  if(dob == 'Invalid Date') {
    alert("Not a Valid Date - use 'YYYY-MM-DD'");
    return false;
  }
  var Cdate = new Date;
  var age = Math.floor((( Cdate - dob) /1000 /(60*60*24)) / 365.25 );
  document.getElementById(thisAge).value = age;
  return true;
}

function TimeStamp(thisDate)
{
  var currentTime=new Date();
  var myStamp= currentTime.getFullYear();
  var myMonth= "00" + (currentTime.getMonth()+1);
  myMonth= myMonth.slice(-2);
  var myDays= "00" + currentTime.getDate();
  myDays= myDays.slice(-2);
  var myHours= "00" + currentTime.getHours();
  myHours= myHours.slice(-2);
  var myMinutes= "00" + currentTime.getMinutes();
  myMinutes= myMinutes.slice(-2);
  var mySeconds= "00" + currentTime.getSeconds();
  mySeconds= mySeconds.slice(-2);
  myStamp= myStamp + "-" + myMonth + "-" + myDays + " " + myHours + ":" +
           myMinutes + ":" + mySeconds;

  document.getElementById(thisDate).value = myStamp;
}

function SetDatetoToday(thisDate)
{
  var currentTime=new Date();
  var myStamp= currentTime.getFullYear();
  var myMonth= "00" + (currentTime.getMonth()+1);
  myMonth= myMonth.slice(-2);
  var myDays= "00" + currentTime.getDate();
  myDays= myDays.slice(-2);
  myStamp= myStamp + "-" + myMonth + "-" + myDays;

  document.getElementById(thisDate).value = myStamp;
}

function ClearThisField(thisField)
{
  if(thisField) {
    document.getElementById(thisField).value= '';
  }
}

//
// These are the functions that support all the inner-block 'Windows'
//
function SubmitSurgery(base,wrap,formID)
{
  document.forms[0].action=base+'?mode=surg&wrap='+wrap;
	if(formID != '' && formID != 0 && formID != null) {
  	document.forms[0].action=base+'?mode=surg&wrap='+wrap+'&id='+formID;
	}
	document.forms[0].submit();
}

function UpdateSurgery(base,wrap,itemID,formID) {
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Surgery ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('ps_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=updatesurg&wrap='+wrap+'&itemID='+itemID;
	if(formID != '' && formID != 0 && formID != null) {
 		document.forms[0].action=base+'?mode=updatesurg&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function DeleteSurgery(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Surgery ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('ps_id_'+itemID).value;
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
	if(confirm("    Delete This Surgery?\n\nThis Action Can Not Be Reversed!")) {

  	document.forms[0].action=base+'?mode=delsurg&wrap='+wrap+'&itemID='+itemID;
		if(formID != '' && formID != 0 && formID != null) {
  		document.forms[0].action=base+'?mode=delsurg&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
		}
		document.forms[0].submit();
	}
	return false;
}

function SubmitHospitalization(base,wrap,formID)
{
  document.forms[0].action=base+'?mode=hosp&wrap='+wrap;
	if(formID != '' && formID != 0 && formID != null) {
  	document.forms[0].action=base+'?mode=hosp&wrap='+wrap+'&id='+formID;
	}
	document.forms[0].submit();
}

function UpdateHospitalization(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Admittance ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('hosp_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=updatehosp&wrap='+wrap+'&itemID='+itemID;
	if(formID != '' && formID != 0 && formID != null) {
 		document.forms[0].action=base+'?mode=updatehosp&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function DeleteHospitalization(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid History ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('hosp_id_'+itemID).value;
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
	if(confirm("    Delete This Admittance?\n\nThis Action Can Not Be Reversed!")) {

  	document.forms[0].action=base+'?mode=delhosp&wrap='+wrap+'&itemID='+itemID;
		if(formID != '' && formID != 0 && formID != null) {
  		document.forms[0].action=base+'?mode=delhosp&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
		}
		document.forms[0].submit();
	}
	return false;
}


function SubmitMedicalHistory(base,wrap,formID)
{
  document.forms[0].action=base+'?mode=pmh&wrap='+wrap;
  if(formID != '' && formID != 0) {
 		document.forms[0].action=base+'?mode=pmh&wrap='+wrap+'&id='+formID;
	}
	document.forms[0].submit();
}

function UpdateMedicalHistory(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Medical History ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('pmh_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=updatepmh&wrap='+wrap+'&itemID='+itemID;
  if(formID != '' && formID != 0) {
 	document.forms[0].action=base+'?mode=updatepmh&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function DeleteMedicalHistory(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid History ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('pmh_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
	if(confirm("  Delete This Medical History?\n\n"+
											"This Action Can Not Be Reversed!")) {

  	document.forms[0].action=base+'?mode=delpmh&wrap='+wrap+'&itemID='+itemID;
  	if(formID != '' && formID != 0 && formID != null) {
  		document.forms[0].action=base+'?mode=delpmh&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
		}
		document.forms[0].submit();
	}
	return false;
}

function SubmitImageHistory(base,wrap,formID)
{
  document.forms[0].action=base+'?mode=img&wrap='+wrap;
  if(formID != '' && formID != 0) {
 		document.forms[0].action=base+'?mode=img&wrap='+wrap+'&id='+formID;
	}
	document.forms[0].submit();
}

function UpdateImageHistory(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Image History ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('img_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=updateimg&wrap='+wrap+'&itemID='+itemID;
  if(formID != '' && formID != 0) {
 	document.forms[0].action=base+'?mode=updateimg&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function DeleteImageHistory(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid History ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('img_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
	if(confirm("  Delete This Image History?\n\n"+
											"This Action Can Not Be Reversed!")) {

  	document.forms[0].action=base+'?mode=delimg&wrap='+wrap+'&itemID='+itemID;
  	if(formID != '' && formID != 0 && formID != null) {
  		document.forms[0].action=base+'?mode=delimg&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
		}
		document.forms[0].submit();
	}
	return false;
}

function SubmitFamilyHistory(base,wrap,formID)
{
  document.forms[0].action=base+'?mode=fh&wrap='+wrap;
  if(formID != '' && formID != 0 && formID != null) {
  	document.forms[0].action=base+'?mode=fh&wrap='+wrap+'&id='+formID;
	}
	document.forms[0].submit();
}

function UpdateFamilyHistory(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Family History ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('fh_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
	document.forms[0].action=base+'?mode=updatefh&wrap='+wrap+'&itemID='+itemID;
  if(formID != '' && formID != 0 && formID != null) {
		document.forms[0].action=base+'?mode=updatefh&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function DeleteFamilyHistory(base,wrap,itemID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Family History ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('fh_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
	if(confirm("  Delete This Family History?\n\nThis Action Can Not Be Reversed!")) {

  	document.forms[0].action=base+'?mode=delfh&wrap='+wrap+'&itemID='+itemID;
  	if(formID != '' && formID != 0 && formID != null) {
  		document.forms[0].action=base+'?mode=delfh&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
		}
		document.forms[0].submit();
	}
	return false;
}

function ToggleDiagWindowMode(base,wrap,formID,mode)
{
  document.forms[0].action=base+'?mode=window&disp='+mode+'&wrap='+wrap;
 	if(formID != '' && formID != 0 && formID != null) {
  	document.forms[0].action=base+'?mode=window&disp='+mode+'&wrap='+wrap+'&id='+formID;
	}
	document.forms[0].submit();
}

function AddDiagnosis(base,wrap,formID)
{
  document.forms[0].action=base+'?mode=diag&wrap='+wrap;
 	if(formID != '' && formID != 0 && formID != null) {
  	document.forms[0].action=base+'?mode=diag&wrap='+wrap+'&id='+formID;
	}
	document.forms[0].submit();
}

function UpdateDiagnosis(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Diagnosis ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('dg_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
	document.forms[0].action=base+'?mode=updatediag&wrap='+wrap+'&itemID='+itemID;
 	if(formID != '' && formID != 0 && formID != null) {
		document.forms[0].action=base+'?mode=updatediag&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function DeleteDiagnosis(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Diagnosis ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('dg_id_'+itemID).value;
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
	if(confirm("      Delete This Diagnosis?\n\nThis Action Can Not Be Reversed!")) {

  	document.forms[0].action=base+'?mode=deldiag&wrap='+wrap+'&itemID='+itemID;
 		if(formID != '' && formID != 0 && formID != null) {
  		document.forms[0].action=base+'?mode=deldiag&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
		}
		document.forms[0].submit();
	}
}

function UnlinkDiagnosis(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Diagnosis ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('dg_id_'+itemID).value;
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=unlinkdiag&wrap='+wrap+'&itemID='+itemID;
 	if(formID != '' && formID != 0 && formID != null) {
 		document.forms[0].action=base+'?mode=unlinkdiag&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function LinkDiagnosis(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Diagnosis ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('dg_id_'+itemID).value;
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=linkdiag&wrap='+wrap+'&itemID='+itemID;
 	if(formID != '' && formID != 0 && formID != null) {
 		document.forms[0].action=base+'?mode=linkdiag&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function UpdatePrescription(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Prescription ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('med_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=updatemed&wrap='+wrap+'&itemID='+itemID;
	if(formID != '' && formID != 0 && formID != null) {
 		document.forms[0].action=base+'?mode=updatemed&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function UpdatePrescriptionHistory(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Prescription ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('med_hist_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=updatemedhist&wrap='+wrap+'&itemID='+itemID;
	if(formID != '' && formID != 0 && formID != null) {
 		document.forms[0].action=base+'?mode=updatemedhist&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function UpdateMedication(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Medication ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('med_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=updatemed&wrap='+wrap+'&itemID='+itemID;
	if(formID != '' && formID != 0 && formID != null) {
 		document.forms[0].action=base+'?mode=updatemed&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function SubmitAllergy(base,wrap,formID)
{
  document.forms[0].action=base+'?mode=all&wrap='+wrap;
	if(formID != '' && formID != 0) {
  	document.forms[0].action=base+'?mode=all&wrap='+wrap+'&id='+formID;
	}
	document.forms[0].submit();
}

function UpdateAllergy(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Allergy ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('all_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=updateall&wrap='+wrap+'&itemID='+itemID;
	if(formID != '' && formID != 0 && formID != null) {
 		document.forms[0].action=base+'?mode=updateall&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function UpdateImmunization(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid Immunization ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('imm_id_'+itemID).value;
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid list entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=updateimm&wrap='+wrap+'&itemID='+itemID;
  if(formID != '' && formID != 0 && formID != null) {
 		document.forms[0].action=base+'?mode=updateimm&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function AdjustFocus(here)
{
	document.forms[0].elements[here].focus();
}

function ExitCheckPopup(here)
{
	var url=document.forms[0].elements[here].value;
	alert("Url: "+url);
	if(confirm("Exit Without Saving...Are You Sure?")) {
		top.restoreSession();
		window.location=url;
		return true;
	} else {
		return false;
	}
}

function toggleGynExamNull()
{
  var i;
  var l = document.forms[0].elements.length;
  for (i=0; i<l; i++) {
    if(document.forms[0].elements[i].name.indexOf("gyn_") == 0) {
			if(document.forms[0].elements[i].type.indexOf('check') != -1) {
      	document.forms[0].elements[i].checked = false;
			}
			if(document.forms[0].elements[i].type.indexOf('text') != -1) {
      	document.forms[0].elements[i].value = '';
			}
    }
  }
}

function toggleGynExamNormal()
{
  var i;
  var l = document.forms[0].elements.length;
  for (i=0; i<l; i++) {
    if(document.forms[0].elements[i].name.indexOf("gyn_") == 0) {
			// First clear all the entries
			if(document.forms[0].elements[i].type.indexOf('check') != -1) {
      	document.forms[0].elements[i].checked = false;
				// Then recheck it if it's the normal one
    		if(document.forms[0].elements[i].name.indexOf("_wnl") != -1) {
      		document.forms[0].elements[i].checked = true;
				}
			}
			if(document.forms[0].elements[i].type.indexOf('text') != -1) {
      	document.forms[0].elements[i].value = '';
			}
    }
  }
}

function SubmitRTO(base,wrap,formID)
{
  document.forms[0].action=base+'?mode=rto&wrap='+wrap;
  if(formID != '' && formID != 0) {
 		document.forms[0].action=base+'?mode=rto&wrap='+wrap+'&id='+formID;
	}
	document.forms[0].submit();
}

function UpdateRTO(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid RTO ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('rto_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid RTO entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=updaterto&wrap='+wrap+'&itemID='+itemID;
  if(formID != '' && formID != 0) {
 	document.forms[0].action=base+'?mode=updaterto&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function RemindRTO(base,wrap,itemID,formID)
{
  if(itemID == '' || itemID == 0 || isNaN(itemID)) {
		alert("No Valid RTO ID Was Found...Aborting!!");
		return false;
	}
	var test=document.getElementById('rto_id_'+itemID);
	if(test == '' || test == null) {
		alert("ID <"+itemID+"> is not a valid RTO entry...Aborting");
		return false;
	}
 	document.forms[0].action=base+'?mode=remindrto&wrap='+wrap+'&itemID='+itemID;
  if(formID != '' && formID != 0) {
 	document.forms[0].action=base+'?mode=remindrto&wrap='+wrap+'&itemID='+itemID+'&id='+formID;
	}
	document.forms[0].submit();
}

function SetRTOStatus(thisStatus)
{
	if(document.forms[0].elements[thisStatus].selectedIndex == 0) {
		document.forms[0].elements[thisStatus].selectedIndex = 1;
	}
}

