<?php

//Define the ends of the lessons
$timetable = [830, 925, 1025, 1120, 1215, 1330, 1425, 1520, 1615, 1710, 1805];

//Define the A/B weeks (Mondays)
date_default_timezone_set('Europe/Zurich');
$a_b_week = array(
  array('2014-08-11', '2014-08-25', '2014-09-08', '2014-09-22', '2014-10-27', '2014-11-10', '2014-12-01', '2014-12-15', '2015-01-12', '2015-01-26'), //week A
  array('2014-08-18', '2014-09-01', '2014-09-15', '2014-10-20', '2014-11-03', '2014-11-24', '2014-12-08', '2015-01-05', '2015-01-19')); //week B


//additional files like the cache manifest and images will have this prefix (like the html <base>).
$out_base = '../'; // [For appspot: '/']

if(!defined('NO_PREVIEW')){
  $json = json_decode(file_get_contents('plans.json'), true);
  
  $file = file_get_contents($json[0]['source']); //enter an example to preview
  $name = $json[0]['name'];
  echo convert_plan($file, $name);
}




function convert_plan($file, $name){
  $file = utf8_encode($file);
  //Extract main table
  $startstr = '<TABLE border="3" rules="all" cellpadding="1" cellspacing="1">';
  $startpos = strpos($file, $startstr);
  $stoppos = strpos($file, '</TABLE><TABLE ', $startpos);
  $file = substr($file, $startpos + strlen($startstr), $stoppos - $startpos - strlen($startstr));

  //strip some tags and attributes
  $file = str_replace(array(
    '</font>',
    '<font size="2" face="Arial">',
    '<font size="3" face="Arial">',
    '<font size="3" face="Arial"  color="#000000">',
    ' align="center"',
    ' nowrap="1"',
    ' nowrap=1',
    ' width="25%"',
    '</TR>',
    '</TD>'
    ), '', $file);

  //every lesson takes two rows, so all rowspans are a multiple of two
  $file = preg_replace('/<TR>\s*<TR>/', '<TR>', $file);
  $file = substr($file, 0, strrpos($file, '<TR>'));
  $file = preg_replace_callback('/ rowspan=(\d+)/', 'replace_rowspan', $file);

  $pos = 0;
  $table_row = -1;
  $table = array();
  while(true){
    $tr_pos = strpos($file, '<TR>', $pos);
    $td_pos = strpos($file, '<TD', $pos);
    if($tr_pos === false && $td_pos === false){
      break;
    }
    if($table_row == -1 || ($tr_pos !== false && $tr_pos < $td_pos)){
      $table_row++;
      $table[$table_row] = array();
      $pos = $tr_pos + 4;
    }else{

      //read col/rowspan attrs
      $colspan = 1;
      $rowspan = 1;
      $attrs = explode(' ', substr($file, $td_pos + 3, strpos($file, '>', $td_pos) - $td_pos - 3));
      foreach($attrs as $attr){
        $parts = explode('=', $attr);
        switch($parts[0]){
        case 'rowspan':
          $rowspan = intval(trim($parts[1], '"\' '));
          if(!$rowspan){
            $rowspan = 1;
          }
          break;
        case 'colspan':
          $colspan = intval(trim($parts[1], '"\' '));
          if(!$colspan){
            $colspan = 1;
          }
          break;
        }
      }
      
      $table_end_pos = strpos($file, '</TABLE>', $td_pos);
      $text = substr($file, $td_pos + 4, $table_end_pos - $td_pos - 4);
      $text = explode('<TR>', $text);
      array_shift($text);
      $text_rows = array();
      foreach($text as $row){
        $text_cols = preg_replace('/<TD[^>]+>/', '<TD>', $row);
        $text_cols = explode('<TD>', $text_cols);
        array_shift($text_cols);
        $fragments = array();
        foreach($text_cols as $text_fragment){
          $text_fragment = trim($text_fragment);
          if(preg_match('/^[0-9]+\)$/', $text_fragment)){//remove 'links' to footnotes like 1)
            continue;
          }
          if($text_fragment != ''){
            $fragments[] = $text_fragment;
          }
        }
        $line = trim(implode(' ', $fragments), ', ');
        if($line != ''){
          $text_rows[] = $line;
        }
      }
      
      $classname = '';
      if(in_array('A', $text_rows)){
        $classname = 'a';
      }elseif(in_array('B', $text_rows)){
        $classname = 'b';
      }
      
      $table[$table_row][] = array(
        'colspan' => $colspan,
        'rowspan' => $rowspan,
        'attrs' => $attrs,
        'text' => $text_rows,
        'class' => $classname
      );
      $pos = $table_end_pos + 8;
    }
  }

  //Remove empty rows at the end
  $firstempty = 0;
  foreach($table as $i => $row){
    foreach($row as $j => $cell){
      if($j != 0 && ($cell['rowspan'] != 1 || implode('', $cell['text']) != '') &&
        $firstempty < $i + $cell['rowspan']){
        $firstempty = $i + $cell['rowspan'];
      }
    }
  }
  while($firstempty < count($table)){
    array_pop($table);
  }

  //print_r($table);
  
  
  global $a_b_week, $timetable;
  $week_js = array();
  foreach($a_b_week as $i => $w){
    $week_js[$i] = array();
    foreach($w as $j){
      array_push($week_js[$i], strtotime($j));
    }
  }

  return make_html_min($name, $table, $timetable, $week_js);
}

//callback for preg_replace_callback
function replace_rowspan($matches){
  if($matches[1] % 2 != 0){
    die('Error: rowspan is not even');
  }
  $m = $matches[1] / 2;
  if($m != 1){
    return ' rowspan=' . $m;
  }
}







function make_html($name, $table, $timetable, $week_js){
global $out_base;
ob_start();

?>
<!doctype html>
<!-- <html manifest="<?php echo $out_base; ?>plan.appcache"> -->
<meta charset="utf-8">
<title>Timetable <?php echo $name; ?></title>
<!-- <link rel="apple-touch-icon-precomposed" sizes="60x60" href="<?php echo $out_base; ?>img/60.png">
<link rel="apple-touch-icon-precomposed" sizes="76x76" href="<?php echo $out_base; ?>img/76.png">
<link rel="apple-touch-icon-precomposed" sizes="120x120" href="<?php echo $out_base; ?>img/120.png">
<link rel="apple-touch-icon-precomposed" sizes="152x152" href="<?php echo $out_base; ?>img/152.png">
<link rel="icon" sizes="1024x1024" type="image/png" href="<?php echo $out_base; ?>img/1024.png">
<link rel="icon" sizes="any" type="image/svg+xml" href="<?php echo $out_base; ?>img/favicon.svg">
<link rel="icon" sizes="16x16 32x32" type="image/vnd.microsoft.icon" href="<?php echo $out_base; ?>favicon.ico"> -->
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
body,
html {
  height: 100%;
  margin: 0;
  padding: 0;
  width: 100%;
  overflow: auto;
}

table {
  border-width: 0;
  border-collapse: collapse;
  border-style: hidden;
  height: 100%;
  width: 100%;
}

td {
  border: 1px solid black;
  font: 1em Arial;
  padding: 0;
  text-align: center;
  white-space: nowrap;
}

tr:first-child td {
  width: 18.75%;
}

tr:first-child td:first-child {
  width: 6.25%;
}

tr:first-child {
  font-size: 1.1em;
}

.t {
  background: #ff7;
}

.n {
  background: #7f7;
}

/* precalculated overlay rgba(238, 238, 238, 0.7) */
.w1 .a, .w0 .b {
    color: #a7a7a7;
    background: #f2f2f2;
}
.w1 .a.t, .w0 .b.t {
    background: #f2f2ca;
}
.w1 .a.n, .w0 .b.n {
    background: #caf2ca;
}

@media only screen and (orientation:portrait) {
  td:not(:first-child):not(.t):not(.n) {
    display: none;
  }
}

@media (max-height:451px) { /* 22 + 39 * rows */
  body {
    font-size: 10px;
  }
}
</style>
<script>
(function (timetable, ABWeek) {
  'use strict';

  var tds, tdClasses = [], rows = [], curTime, hidden, timeoutId,
    table;
  function update() {
    var now = new Date('13 Aug 2014 10:25:00'),
      day = now.getDay(),
      time = now.getHours() * 100 + now.getMinutes(),
      date = now.getTime(),
      i,
      j,
      lesson = 0;
    if (curTime !== time) {
      //reset classes
      for (i = 0; i < tds.length; i += 1) {
        tds[i].className = tdClasses[i];
      }

      if (day >= 1 && day <= 5) {
        while (lesson < timetable.length && timetable[lesson] <= time) {
          lesson += 1;
        }

        //mark all fields of today
        for (i = 0; i < rows[day].length; i += 1) {
          for (j = 0; j < rows[day][i].length; j += 1) {
            rows[day][i][j].className += lesson === i - 1 ? ' n' : ' t';
          }
        }
      }

fori: for (i = 0; i < 2; i += 1) {
        for (j = 0; j < ABWeek[i].length; j += 1) {
          if (1000 * (ABWeek[i][j] - 60 * 60 * 24 * 2) < date && date < 1000 * (ABWeek[i][j] + 60 * 60 * 24 * 5)) {
            document.body.className = 'w' + i;
            break fori;
          }
        }
      }

      curTime = time;
    }
    timeoutId = setTimeout(update, 1000);
  }


  function handleVisibility() {
    clearTimeout(timeoutId);
    if (!document[hidden]) {
      update();
    }
  }

  function fontResize() {
    var windowWidth = document.documentElement.clientWidth || document.body.clientWidth,
      windowHeight = document.documentElement.clientHeight || document.body.clientHeight,
      fontSize = 16;
    document.body.style.fontSize = fontSize + 'px';
    while (fontSize > 6 && (table.offsetWidth - 1 > windowWidth || table.offsetHeight - 1 > windowHeight)) { //tolerate one pixel more in case of floating point errors
      fontSize -= 1;
      document.body.style.fontSize = fontSize + 'px';
    }
  }

  window.onload = function () {
    var trs = document.getElementsByTagName('tr'), r = trs.length,
      tCells = [], tCols = [], tIs = [],
      shortest, shortestLength, c,
      i, j,
      visibilityChange;

    tds = document.getElementsByTagName('td');
    for (i = 0; i < tds.length; i += 1) {
      tdClasses[i] = tds[i].className;
    }

    //Create empty Array structure
    for (i = 1; i <= 5; i += 1) {
      rows[i] = [];
      for (j = 0; j <= timetable.length; j += 1) {
        rows[i][j] = [];
      }
    }

    //Fill the Array structure
    for (i = 0; i < r; i += 1) {
      tCells[i] = trs[i].getElementsByTagName('td');
      tCols[i] = 0;
      tIs[i] = 1;
    }
    for (i = 1; i <= 5; i += 1) {
      while (true) {
        shortest = 0;
        shortestLength = tCols[0];
        for (j = 1; j < r; j += 1) {
          if (tCols[j] < shortestLength) {
            shortest = j;
            shortestLength = tCols[j];
          }
        }
        if (shortestLength === i * 12) {
          break;
        }
        c = tCells[shortest][tIs[shortest]];
        for (j = 0; j < c.rowSpan; j += 1) {
          tCols[shortest + j] += c.colSpan;
          rows[i][shortest + j].push(c);
        }
        tIs[shortest] += 1;
      }
    }

    update();

    table = document.getElementsByTagName('table')[0];
    fontResize();
    window.onresize = fontResize;

    if (document.hidden !== undefined) {
      hidden = "hidden";
      visibilityChange = "visibilitychange";
    } else if (document.webkitHidden !== undefined) {
      hidden = "webkitHidden";
      visibilityChange = "webkitvisibilitychange";
    } else {
      return;
    }
    document.addEventListener(visibilityChange, handleVisibility, false);
  };
}(<?php echo json_encode($timetable, JSON_PRETTY_PRINT); ?>,
  <?php echo json_encode($week_js, JSON_PRETTY_PRINT); ?>));
</script>

<table>
<?php
foreach($table as $row){
  echo '  <tr>
';
  foreach($row as $cell){
    $attrs = '';
    if($cell['class'] != ''){
      $attrs .= ' class="' . $cell['class'] . '"';
    }
    if($cell['rowspan'] > 1){
      $attrs .= ' rowspan="' . $cell['rowspan'] . '"';
    }
    if($cell['colspan'] > 1){
      $attrs .= ' colspan="' . $cell['colspan'] . '"';
    }
    echo '    <td' . $attrs . '>';
    echo implode('<br>
      ', $cell['text']) . '
';
  }
}
?>
</table>
<?php

return ob_get_clean();
}




function make_html_min($name, $table, $timetable, $week_js){
global $out_base;
ob_start();

?>
<!doctype html><html manifest="<?php echo $out_base; ?>plan.appcache"><meta charset="utf-8"><title>Timetable <?php echo $name; ?></title><?php ?>
<link rel="apple-touch-icon-precomposed" sizes="60x60" href="<?php echo $out_base; ?>img/60.png"><link rel="apple-touch-icon-precomposed" sizes="76x76" href="<?php echo $out_base; ?>img/76.png"><link rel="apple-touch-icon-precomposed" sizes="120x120" href="<?php echo $out_base; ?>img/120.png"><link rel="apple-touch-icon-precomposed" sizes="152x152" href="<?php echo $out_base; ?>img/152.png"><?php ?>
<link rel="icon" sizes="1024x1024" type="image/png" href="<?php echo $out_base; ?>img/1024.png"><link rel="icon" sizes="any" type="image/svg+xml" href="<?php echo $out_base; ?>img/favicon.svg"><link rel="icon" sizes="16x16 32x32" type="image/vnd.microsoft.icon" href="<?php echo $out_base; ?>favicon.ico"><?php ?>
<meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="viewport" content="width=device-width,initial-scale=1.0"><?php ?>
<style>body,html{height:100%;margin:0;padding:0;width:100%;overflow:auto;}table{border-width:0;border-collapse:collapse;border-style:hidden;height:100%;width:100%;}td{border:1px solid black;font:1em Arial;padding:0;text-align:center;white-space:nowrap;}tr:first-child td{width:18.75%;}tr:first-child td:first-child{width:6.25%;}tr:first-child{font-size:1.1em;}.t{background:#ff7;}.n{background:#7f7;}.w1 .a,.w0 .b{color:#a7a7a7;background:#f2f2f2;}.w1 .a.t,.w0 .b.t{background:#f2f2ca;}.w1 .a.n,.w0 .b.n{background:#caf2ca;}@media only screen and (orientation:portrait){td:not(:first-child):not(.t):not(.n){display:none;}}@media (max-height:451px){body{font-size:10px;}}</style><?php ?>
<script>(function(m,n){function p(){var c=new Date(),f=c.getDay(),h=100*c.getHours()+c.getMinutes(),c=c.getTime(),a,e,g=0;if(s!==h){for(a=0;a<l.length;a+=1)l[a].className=t[a];if(1<=f&&5>=f){for(;g<m.length&&m[g]<=h;)g+=1;for(a=0;a<k[f].length;a+=1)for(e=0;e<k[f][a].length;e+=1)k[f][a][e].className+=g===a-1?" n":" t"}a=0;a:for(;2>a;a+=1)for(e=0;e<n[a].length;e++)if(1E3*(n[a][e]-172800)<c&&c<1E3*(n[a][e]+432E3)){document.body.className="w"+a;break a}s=h}u=setTimeout(p,1E3)}function w(){clearTimeout(u);document[q]||p()}function v(){var c=document.documentElement.clientWidth||document.body.clientWidth,f=document.documentElement.clientHeight||document.body.clientHeight,h=16;for(document.body.style.fontSize=h+"px";6<h&&(r.offsetWidth-1>c||r.offsetHeight-1>f);)h-=1,document.body.style.fontSize=h+"px"}var l,t=[],k=[],s,q,u,r;window.onload=function(){var c=document.getElementsByTagName("tr"),f=c.length,h=[],a=[],e=[],g,b,d;l=document.getElementsByTagName("td");for(b=0;b<l.length;b+=1)t[b]=l[b].className;for(b=1;5>=b;b+=1)for(k[b]=[],d=0;d<=m.length;d+=1)k[b][d]=[];for(b=0;b<f;b+=1)h[b]=c[b].getElementsByTagName("td"),a[b]=0,e[b]=1;for(b=1;5>=b;b+=1)for(;;){c=0;g=a[0];for(d=1;d<f;d+=1)a[d]<g&&(c=d,g=a[d]);if(g===12*b)break;g=h[c][e[c]];for(d=0;d<g.rowSpan;d+=1)a[c+d]+=g.colSpan,k[b][c+d].push(g);e[c]+=1}p();r=document.getElementsByTagName("table")[0];v();window.onresize=v;if("undefined"!==typeof document.hidden)q="hidden",f="visibilitychange";else if("undefined"!==typeof document.webkitHidden)q="webkitHidden",f="webkitvisibilitychange";else return;document.addEventListener(f,w,!1)}})(<?php
echo json_encode($timetable) . "," . json_encode($week_js);
?>);</script><?php ?>
<table><?php
foreach($table as $row){
  echo '<tr>';
  foreach($row as $cell){
    $attrs = '';
    if($cell['class'] != ''){
      $attrs .= ' class="' . $cell['class'] . '"';
    }
    if($cell['rowspan'] > 1){
      $attrs .= ' rowspan="' . $cell['rowspan'] . '"';
    }
    if($cell['colspan'] > 1){
      $attrs .= ' colspan="' . $cell['colspan'] . '"';
    }
    echo '<td' . $attrs . '>';
    echo implode('<br>', $cell['text']);
  }
}
?></table><?php

return ob_get_clean();
}