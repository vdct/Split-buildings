<?php
/*
    "Split" is intended to split into smaller parts large .osm files of buildings
    mainly extracted from the French Cadastre.
    Copyright (C) 2010 Vincent de Château-Thierry (vdct at laposte dot net)
 
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.
 
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.
 
    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
// Fichier en input
if (!$argv[1]) 
{
	echo "\nUSAGE : split.php <fichier .osm> \n";
	return;
}
 
$debut = time();
 
$input_fname = $argv[1];
$racine_fname = substr($input_fname,0,strlen($input_fname)-4);
 
// lecture
$f = file_get_contents($input_fname);
 
// parsing
$p = xml_parser_create();
xml_parser_set_option($p,XML_OPTION_CASE_FOLDING,0); 
xml_parser_set_option($p,XML_OPTION_SKIP_WHITE,1); 
xml_parse_into_struct($p, $f, $vals, $index);
xml_parser_free($p);
echo "\nTraitement de ".$input_fname."\n";
echo "\nEtape 0 ".date("i:s",time() - $debut)."\n";
$etape = time();
 
// indice des noeuds, ways et ref_node
$idx_nodes	= $index["node"];
$idx_ways	= $index["way"];
$idx_nd	= $index["nd"];
 
// passage en memoire des noeuds
$array_points = array();
 
// id OSM => indice XML
foreach ($idx_nodes as $n){
	$array_points[$vals[$n]['attributes']['id']] = $n;
}
 
echo "\nEtape 1 ".date("i:s",time() - $etape)."\n";
$etape = time();
 
// emprises par building et emprise totale
// demarrage avec le premier point du fichier
 
$emprise_totale = array($vals[$idx_nodes[0]]['attributes']['lon'],$vals[$idx_nodes[0]]['attributes']['lat'],$vals[$idx_nodes[0]]['attributes']['lon'],$vals[$idx_nodes[0]]['attributes']['lat']);
$array_emprises_ways = array();
 
 
for ($w=0;$w<count($idx_ways)/2;$w++)
{
	$array_nd = slice_by_value($idx_nd,$idx_ways[$w * 2],$idx_ways[($w * 2)+1]);
	$idx_pt0 = $array_points[$vals[$array_nd[0]]['attributes']['ref']];
	$x = $vals[$idx_pt0]['attributes']['lon'];
	$y = $vals[$idx_pt0]['attributes']['lat'];
	$emprise_way = array($x,$y,$x,$y);
	$emprise_totale = expand_rectangle_to_point($emprise_totale,$x,$y);
 
	foreach ($array_nd as $nd){
		$idx_pt = $array_points[$vals[$nd]['attributes']['ref']];
		$x = $vals[$idx_pt]['attributes']['lon'];
		$y = $vals[$idx_pt]['attributes']['lat'];
		$emprise_way = expand_rectangle_to_point($emprise_way,$x,$y);
	}
	$emprise_totale = expand_rectangle_to_point($emprise_totale,$emprise_way[0],$emprise_way[1]);
	$emprise_totale = expand_rectangle_to_point($emprise_totale,$emprise_way[2],$emprise_way[3]);
 
	$array_emprises_ways[] = array("emprise" => $emprise_way, "open_at" => $idx_ways[$w*2], "closed_at" => $idx_ways[($w*2)+1]);
}
echo "\nEtape 2 ".date("i:s",time() - $etape)."\n";
$etape = time();
 
$array_global = array();
// Quarts d'emprises
$empriseHG = array($emprise_totale[0],($emprise_totale[1]+$emprise_totale[3])/2,($emprise_totale[0]+$emprise_totale[2])/2,$emprise_totale[3]);
$empriseHD = array(($emprise_totale[0]+$emprise_totale[2])/2,($emprise_totale[1]+$emprise_totale[3])/2,$emprise_totale[2],$emprise_totale[3]);
$empriseBG = array($emprise_totale[0],$emprise_totale[1],($emprise_totale[0]+$emprise_totale[2])/2,($emprise_totale[1]+$emprise_totale[3])/2);
$empriseBD = array(($emprise_totale[0]+$emprise_totale[2])/2,$emprise_totale[1],$emprise_totale[2],($emprise_totale[1]+$emprise_totale[3])/2);
 
// tableaux des buildings par 1/4
$array_HG = array();
$array_HD = array();
$array_BG = array();
$array_BD = array();
 
// boucle sur les ways
for ($w=0;$w < count($idx_ways)/2;$w++){
	$emprise = $array_emprises_ways[$w]["emprise"];
	if (overlap($emprise,$empriseHG) == 1){
		$array_HG[] = $array_emprises_ways[$w];
		continue;
	}
	if (overlap($emprise,$empriseHD) == 1){
		$array_HD[] = $array_emprises_ways[$w];
		continue;
	}
	if (overlap($emprise,$empriseBG) == 1){
		$array_BG[] = $array_emprises_ways[$w];
		continue;
	}
	$array_BD[] = $array_emprises_ways[$w];
}
echo "\nEtape 3 ".date("i:s",time() - $etape)."\n";
$etape = time();
 
//liste des points par array de ways
$pts_HG = array();
$pts_HD = array();
$pts_BG = array();
$pts_BD = array();
 
$a_ways = array($array_HG,$array_HD,$array_BG,$array_BD);
$a_portion = array("A","B","C","D");
$a_pts = array();
 
foreach ($a_ways as $current_a_ways){
	$current_a_pts = array();
	foreach ($current_a_ways as $cw){
		for ($s = $cw["open_at"];$s < $cw["closed_at"];$s++){
			if ($vals[$s]['tag'] == 'nd'){
				$current_a_pts[$vals[$s]['attributes']['ref']] = 1;
			}
		}
	}
	$a_pts[] = array_keys($current_a_pts);
}
echo "\nEtape 4 ".date("i:s",time() - $etape)."\n";
$etape = time();
 
for ($f = 0;$f < count($a_portion);$f++){
	$fname = $racine_fname.'_'.$a_portion[$f].'.osm';
	if (is_file($fname)) unlink($fname);
	$fpr = fopen($racine_fname.'_'.$a_portion[$f].'.osm','w');
	fwrite($fpr,'<?xml version=\'1.0\' encoding=\'UTF-8\'?>');
	fwrite($fpr,compose_xml_line($vals[0]));
 
	$cur_pts = $a_pts[$f];
	$cur_way = $a_ways[$f];
 
	foreach ($cur_pts as $p){
		fwrite($fpr,compose_xml_line($vals[$array_points[$p]]));
	}
	for ($p = 0;$p < count($cur_way);$p++){
		for ($l = $cur_way[$p]['open_at'];$l <= $cur_way[$p]['closed_at'];$l++){
			fwrite($fpr,compose_xml_line($vals[$l]));
		}
	}
	fwrite($fpr,compose_xml_line($vals[count($vals)-1]));
	fclose($fpr);
}
 
$fin = time();
 
echo "\nFin du traitement de ".$input_fname."\n";
echo "Duree totale : ".date("i:s",$fin - $debut);
 
function compose_xml_line($val){
	if ($val['type']=='complete'||$val['type']=='open'){
		$s = "\n".'<'.$val['tag'];
		$att = array_keys($val['attributes']);
		for ($a=0;$a<count($att);$a++){
			$s = $s.' '.$att[$a].'=\''.$val['attributes'][$att[$a]].'\'';
		}
	}
	if ($val['type']=='complete') $s = $s.' />';
	if ($val['type']=='open') $s = $s.'>';
	if ($val['type']=='close') $s = "\n".'</'.$val['tag'].'>';
 
	return $s;
}
 
function expand_rectangle_to_point($array_emprise1,$x,$y){
	$xmin = min($array_emprise1[0],$x);
	$ymin = min($array_emprise1[1],$y);
	$xmax = max($array_emprise1[2],$x);
	$ymax = max($array_emprise1[3],$y);
 
	return array($xmin,$ymin,$xmax,$ymax);
}
 
function slice_by_value($array_in,$val_min,$val_max){
	$array_out = array();
	for ($i = 0;$i < count($array_in);$i++){
		if ($array_in[$i] >= $val_min && $array_in[$i] <= $val_max){
			$array_out[] = $array_in[$i];
		}
	}
	return $array_out;	
}
 
function overlap($emp1,$emp2){
	$x1_min = $emp1[0];
	$y1_min = $emp1[1];
	$x1_max = $emp1[2];
	$y1_max = $emp1[3];
 
	$x2_min = $emp2[0];
	$y2_min = $emp2[1];
	$x2_max = $emp2[2];
	$y2_max = $emp2[3];
 
	// cas ou un des 4 coins de 1 est dans l'emprise de 2 (et inversement)
	if (($x1_min >= $x2_min && $x1_min <= $x2_max || $x1_max >= $x2_min && $x1_max <= $x2_max) && ($y1_min >= $y2_min && $y1_min <= $y2_max || $y1_max >= $y2_min && $y1_max <= $y2_max)){
		return 1;
	}
	// cas ou 1 englobe 2
	if ($x1_min <= $x2_min && $x1_max >= $x2_max && $y1_min <= $y2_min && $y1_max >= $y2_max){
		return 1;
	}
	// cas ou 2 englobe 1
	if ($x2_min <= $x1_min && $x2_max >= $x1_max && $y2_min <= $y1_min && $y2_max >= $y1_max){
		return 1;
	}
	// sortie sans interection
	return -1;
}	
 
function debug($content)
{
	print_r($content);
	echo " \n";
}
 
?>