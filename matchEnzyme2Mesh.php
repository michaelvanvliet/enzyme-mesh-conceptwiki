<?php

set_time_limit(0); // make sure it doesn't stop on a time-out

function clearVar($toClean) {

	$toClean = trim($toClean);
	$toClean = str_replace("\r", "", $toClean);
	$toClean = str_replace("\t", "", $toClean);
	$toClean = str_replace("\n", "", $toClean);
	//echo ">>>" . $toClean . "<<<";

	return $toClean;
}

function getPage($url){

	$html = "";

	$urlCode = base64_encode($url);
	$cacheFilePath = './cache/' . substr($urlCode, 0, 80) . '/' . substr($urlCode, 80, 85) . '/' . substr($urlCode, 85, 150);
	$cacheFile = $cacheFilePath . '/' . trim(substr($urlCode, 150, 1000)) . '.html';

	// try to fetch it from cache
	if (file_exists($cacheFile)){
		$html = @file_get_contents($cacheFile);
	} else {
		// not cached yet, retrieving and caching
		@mkdir($cacheFilePath, 0777, true );
		$html = @file_get_contents($url);
		$fp = fopen($cacheFile, 'w');
		fwrite($fp, $html);
	}

	return $html;
}

function getCWuuid($q) {

	$uuid = "";

	if ($q != ''){
		$url = 'http://conceptwiki.nbiceng.net/web-ws/concept/search?q=' . urlencode($q) . '&branch=2&limit=1';
		$json = @getPage($url);
		$jsonParts = explode('"uuid":"',$json);
		$UUIDParts = @explode('"', ($jsonParts[1]));
		$uuid = $UUIDParts[0];
	}

	return $uuid;
}

function getMeshId($meshPage) {

	$meshId = '';

	$UIDParts = explode('<TR><TH align=left>Unique ID</TH><TD colspan=1>',$meshPage);
	$meshParts = @explode('</TD></TR>', ($UIDParts[1]));
	if (is_array($meshParts)){
		if ($meshParts[0]){
			$meshId = clearVar($meshParts[0]);
		}
	}

	return $meshId;
}

function getMeshName($meshPage) {

	$meshName = '';

	// try based on "MeSH Heading"
	$meshHParts = explode('<TR><TH align=left>MeSH Heading</TH><TD colspan=1>',$meshPage);
	$meshParts = @explode('</TD></TR>', ($meshHParts[1]));
	if (is_array($meshParts)){
		if ($meshParts[0]){
			$meshName = $meshParts[0];
		}
	}

	// try based on "Name of Substance"
	if (!$meshName || $meshName == ''){
		$NofSParts = explode('<TR><TH align=left>Name of Substance</TH><TD colspan=1>',$meshPage);
		$meshParts = @explode('</TD></TR>', ($NofSParts[1]));
		if ($meshParts[0]){
			$meshName = $meshParts[0];
		}
	}

	return clearVar($meshName);
}

function getMeshEc($meshPage) {

	$meshEc = "";

	$mECParts = explode('<TR><TH align=left>Registry Number</TH><TD colspan=1>',$meshPage);
	$meshParts = @explode('</TD></TR>', ($mECParts[1]));
	if (is_array($meshParts)){
		if ($meshParts[0]){
			$meshEc = clearVar($meshParts[0]);
		}
	}

	return str_replace("EC ","",$meshEc);
}

function hasHyphen($meshEc) {

	$hasHyphen = "N";

	if (strpos($meshEc,'-') !== false) { $hasHyphen="Y"; }

	return $hasHyphen;
}

function ecIsMatch($ec, $meshEc){

	$match="N";

	if (clearVar($ec) == clearVar($meshEc)) { $match="Y"; }

	return $match;
}

function writeLine($fp, $args) {

	echo "\n writing: " . implode(", ", array_values($args));

	fwrite(	$fp,
			$args['lineCount'] . "\t" .
			$args['hasHyphen'] . "\t" .
			$args['type'] . "\t" .
			$args['match'] . "\t" .
			$args['ec'] . "\t".
			$args['meshEc'] . "\t" .
			$args['meshId'] . "\t" .
			$args['prefLabel'] . "\t" .
			$args['meshName'] . "\t" .
			$args['uuid'] . "\n"
		  );
}

// settings
$limit = 0; //500; // handy for testing
$entries = array(); // used to prevent duplicate entries
$meshURLbase = 'http://www.nlm.nih.gov/cgi/mesh/2013/MB_cgi?mode=&term=';
$meshRecordBase = 'http://www.nlm.nih.gov/cgi/mesh/2013/MB_cgi?mode=&index=';

// Open file and write header
$fp = fopen('enzymes.tab', 'w');
fwrite($fp, "Line\thasHyphen\tType\tMatch\tEC\tmeshEC\tMeSH\tName\tMeshName\tUUID\n");

$EC = "";
$PREF_LABEL = "";

// fetch enzyme.dat
$lines = file('ftp://ftp.expasy.org/databases/enzyme/enzyme.dat');

// start counter
$lineCount = 0;

// look for the ID xxxxxx and DE xxxxxxx lines
foreach ($lines as $idx => $l){

	// check if we should stop early
	if ($limit == 0 || $idx <= $limit){

		// clean the line to parse correctly
		$line = clearVar($l);

		// create an array of line chunks
		$parts = explode("   ", $line);

		if (is_array($parts)){

			//EC
			if ($parts[0] == 'ID'){

				if ($EC){ // this means a new Enzyme starts, clear the cache but first write the previous one

					// lookup mesh ID, Name and EC via Mesh html page
					$lookupUrlPref = $meshURLbase . urlencode(substr(clearVar($PREF_LABEL),0,-1));
					$meshPage = getPage($lookupUrlPref);

					//if (str_replace("No term found", "", $meshPage) == $meshPage){
						// see if we have multiple options to choose from

						$possibleRecords = array();

						if (str_replace("Please select a term from list", "", $meshPage) != $meshPage){

							// fetch options from html
							$altParts = explode('MB_cgi?mode=&index=', $meshPage);
							foreach ($altParts as $altPart){

								$indexIdParts = explode('&field', $altPart);
								$indexId = @$indexIdParts[0];
								if (str_replace("<TITLE>", "", $indexId) ==  $indexId){
									// only use the ones with a correct $indexId
									$possibleRecordUrl = $meshRecordBase . urlencode($indexId);
									$meshPage = getPage($possibleRecordUrl);

									$args = array();
									$args['hasHyphen'] = hasHyphen(getMeshEc($meshPage));
									$args['type'] = "O";
									$args['match'] = ecIsMatch($EC, getMeshEc($meshPage));
									$args['ec'] = $EC;
									$args['meshEc'] = getMeshEc($meshPage);
									$args['meshId'] = getMeshId($meshPage);
									$args['prefLabel'] = substr(clearVar($PREF_LABEL),0,-1);
									$args['meshName'] = getMeshName($meshPage);
									$args['uuid'] = getCWuuid(getMeshName($meshPage));

									$encodedEntry = base64_encode(serialize($args));
									if (!in_array($encodedEntry, $entries)){
										$args['lineCount'] = $lineCount;
										writeLine($fp, $args);
										$entries[] = $encodedEntry;
										$lineCount++;
									}
								}
							}
						} else {
							// only one page found which we can parse.
							$args = array();
							$args['hasHyphen'] = hasHyphen(getMeshEc($meshPage));
							$args['type'] = "U";
							$args['match'] = ecIsMatch($EC, getMeshEc($meshPage));
							$args['ec'] = $EC;
							$args['meshEc'] = getMeshEc($meshPage);
							$args['meshId'] = getMeshId($meshPage);
							$args['prefLabel'] = substr(clearVar($PREF_LABEL),0,-1);
							$args['meshName'] = getMeshName($meshPage);
							$args['uuid'] = getCWuuid(getMeshName($meshPage));

							$encodedEntry = base64_encode(serialize($args));
							if (!in_array($encodedEntry, $entries)){
								$args['lineCount'] = $lineCount;
								writeLine($fp, $args);
								$entries[] = $encodedEntry;
								$lineCount++;
							}
						}
					//} // string no results
				}

				// reset
				$EC = trim($parts[1]);
				$PREF_LABEL = "";
			}

			// NAME
			if ($parts[0] == 'DE'){
				$PREF_LABEL = $PREF_LABEL . clearVar($parts[1]);
			}
		}
	} // line limit
}

fclose($fp);
