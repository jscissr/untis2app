<?php

//Define the ends of the lessons (08:30 = 830)
$timetable = [830, 925, 1025, 1120, 1215, 1330, 1425, 1520, 1615, 1710, 1805];

//Define the A/B weeks (Mondays)
date_default_timezone_set('Europe/Zurich');
$a_b_week = array(
  array('2015-08-10', '2015-08-24', '2015-09-07', '2015-09-21', '2015-10-26', '2015-11-09', '2015-11-30', '2015-12-14', '2016-01-11', '2016-01-25'), //week A
  array(       '2015-08-17', '2015-08-31', '2015-09-14', '2015-10-19', '2015-11-02', '2015-11-23', '2015-12-07', '2015-12-21', '2016-01-18', '2016-02-01')); //week B

//additional files like the cache manifest and images will have this prefix (like the html <base>). Try ../ or /
$out_base = '../'; // [For appspot: '']

if (!defined('NO_PREVIEW')) {
  $json = json_decode(file_get_contents('timetables.json'), true);

  $file = file_get_contents($json[0]['source']); //enter an example to preview
  $name = $json[0]['name'];
  echo convert_timetable($file, $name);
}




function convert_timetable($file, $name) {
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
  while (true) {
    $tr_pos = strpos($file, '<TR>', $pos);
    $td_pos = strpos($file, '<TD', $pos);
    if ($tr_pos === false && $td_pos === false) {
      break;
    }

    if ($table_row == -1 || ($tr_pos !== false && $tr_pos < $td_pos)) {
      $table_row++;
      $table[$table_row] = array();
      $pos = $tr_pos + 4;
    } else {
      //read col/rowspan attrs
      $colspan = 1;
      $rowspan = 1;
      $attrs = explode(' ', substr($file, $td_pos + 3, strpos($file, '>', $td_pos) - $td_pos - 3));
      foreach ($attrs as $attr) {
        $parts = explode('=', $attr);
        switch($parts[0]) {
        case 'rowspan':
          $rowspan = intval(trim($parts[1], '"\' '));
          if (!$rowspan) {
            $rowspan = 1;
          }
          break;
        case 'colspan':
          $colspan = intval(trim($parts[1], '"\' '));
          if (!$colspan) {
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
      foreach ($text as $row) {
        $text_cols = preg_replace('/<TD[^>]+>/', '<TD>', $row);
        $text_cols = explode('<TD>', $text_cols);
        array_shift($text_cols);
        $fragments = array();
        foreach ($text_cols as $text_fragment) {
          $text_fragment = trim($text_fragment);
          if (preg_match('/^[0-9]+\)$/', $text_fragment)) {//remove 'links' to footnotes like 1)
            continue;
          }
          if ($text_fragment != '') {
            $fragments[] = $text_fragment;
          }
        }
        $line = trim(implode(' ', $fragments), ', ');
        if ($line != '') {
          $text_rows[] = $line;
        }
      }

      $classname = '';
      if (in_array('A', $text_rows)) {
        $classname = 'a';
      } elseif (in_array('B', $text_rows)) {
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
  foreach ($table as $i => $row) {
    foreach ($row as $j => $cell) {
      if ($j != 0 && ($cell['rowspan'] != 1 || implode('', $cell['text']) != '') &&
          $firstempty < $i + $cell['rowspan']) {
        $firstempty = $i + $cell['rowspan'];
      }
    }
  }
  while ($firstempty < count($table)) {
    array_pop($table);
  }

  //print_r($table);

  global $a_b_week, $timetable;
  $week_js = array();
  foreach ($a_b_week as $i => $w) {
    $week_js[$i] = array();
    foreach ($w as $j) {
      array_push($week_js[$i], strtotime($j));
    }
  }

  return make_html_min($name, $table, $timetable, $week_js);
}

//callback for preg_replace_callback
function replace_rowspan($matches) {
  if ($matches[1] % 2 != 0) {
    die('Error: rowspan is not even');
  }
  $m = $matches[1] / 2;
  if ($m != 1) {
    return ' rowspan=' . $m;
  }
}




// Unminified version for testing, the minified verision is below!

function make_html($name, $table, $timetable, $week_js) {
  global $out_base;
  ob_start();

  ?>
<!DOCTYPE html>
<!-- <html manifest="<?php echo $out_base; ?>timetable.appcache"> -->
<meta charset="utf-8">
<title>Timetable <?php echo $name; ?></title>
<!-- <link rel="apple-touch-icon" sizes="76x76" href="<?php echo $out_base; ?>img/ios-76.png">
<link rel="apple-touch-icon" sizes="120x120" href="<?php echo $out_base; ?>img/ios-120.png">
<link rel="apple-touch-icon" sizes="152x152" href="<?php echo $out_base; ?>img/ios-152.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo $out_base; ?>img/ios-180.png">
<link rel="icon" sizes="192x192" type="image/png" href="<?php echo $out_base; ?>img/192.png">
<link rel="icon" sizes="any" type="image/svg+xml" href="<?php echo $out_base; ?>img/icon.svg">
<link rel="manifest" href="<?php echo $out_base; ?>manifest.json"> -->
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex">
<style>
html,
body {
  height: 100%;
  margin: 0;
  padding: 0;
  width: 100%;
  overflow: auto;
  color: rgba(0, 0, 0, .87);
  font-family: sans-serif;
  -webkit-text-size-adjust: 100%;
}

table {
  border-spacing: 0;
  height: 100%;
  width: 100%;
}

td {
  border: solid rgba(0, 0, 0, .12);
  border-width: 1px 0 0 1px;
  padding: 0;
  text-align: center;
  white-space: nowrap;
}

td:first-child {
  border-left-width: 0;
}

tr:first-child td {
  width: 18.75%;
  border-top-width: 0;
  line-height: 1.6;
}

tr td:first-child {
  width: 6.25%;
}

.t {
  background: #ffeb3b; /* Yellow 500 (Material Design) */
}

.n {
  background: #1de9b6; /* Teal A400 */
}

/* Disabled */
.w1 .a, .w0 .b {
    color: rgba(0, 0, 0, .26);
    background: #f5f5f5; /* Grey 100 */
}
.w1 .a.t, .w0 .b.t {
    background: #fff9c4; /* Yellow 100 */
}
.w1 .a.n, .w0 .b.n {
    background: #a7ffeb; /* Teal A100 */
}

@media only screen and (orientation:portrait) {
  td:not(:first-child):not(.t):not(.n) {
    display: none;
  }
}

@media (max-height:451px) {
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
    var now = new Date('20 Aug 2015 11:25:00'),
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

    if (window.applicationCache) {
      applicationCache.addEventListener('updateready', function(e) {
        applicationCache.swapCache();
        location.reload();
      }, false);
    }

    if (document.hidden !== undefined) {
      hidden = 'hidden';
      visibilityChange = 'visibilitychange';
    } else if (document.webkitHidden !== undefined) {
      hidden = 'webkitHidden';
      visibilityChange = 'webkitvisibilitychange';
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
foreach ($table as $row) {
  echo '  <tr>
';
  foreach ($row as $cell) {
    $attrs = '';
    if ($cell['class'] != '') {
      $attrs .= ' class="' . $cell['class'] . '"';
    }
    if ($cell['rowspan'] > 1) {
      $attrs .= ' rowspan="' . $cell['rowspan'] . '"';
    }
    if ($cell['colspan'] > 1) {
      $attrs .= ' colspan="' . $cell['colspan'] . '"';
    }
    echo '    <td' . $attrs . '>';
    echo implode('<br>
      ', text_to_html($cell['text'])) . '
';
  }
}
?>
</table>
<?php

  return ob_get_clean();
}




function make_html_min($name, $table, $timetable, $week_js) {
  global $out_base;
  ob_start();

  ?>
<!DOCTYPE html><html manifest="<?php echo $out_base; ?>timetable.appcache"><meta charset="utf-8"><title>Timetable <?php echo $name; ?></title><?php ?>
<link rel="apple-touch-icon" sizes="76x76" href="<?php echo $out_base; ?>img/ios-76.png"><link rel="apple-touch-icon" sizes="120x120" href="<?php echo $out_base; ?>img/ios-120.png"><link rel="apple-touch-icon" sizes="152x152" href="<?php echo $out_base; ?>img/ios-152.png"><link rel="apple-touch-icon" sizes="180x180" href="<?php echo $out_base; ?>img/ios-180.png"><?php ?>
<link rel="icon" sizes="192x192" type="image/png" href="<?php echo $out_base; ?>img/192.png"><link rel="icon" sizes="any" type="image/svg+xml" href="<?php echo $out_base; ?>img/icon.svg"><link rel="manifest" href="<?php echo $out_base; ?>manifest.json"><?php ?>
<meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="viewport" content="width=device-width,initial-scale=1.0"><meta name="robots" content="noindex"><?php ?>
<style>html,body{height:100%;margin:0;padding:0;width:100%;overflow:auto;color:rgba(0,0,0,.87);font-family:sans-serif;-webkit-text-size-adjust:100%;}table{border-spacing:0;height:100%;width:100%;}td{border:solid rgba(0,0,0,.12);border-width:1px 0 0 1px;padding:0;text-align:center;white-space:nowrap;}td:first-child{border-left-width:0;}tr:first-child td{width:18.75%;border-top-width:0;line-height:1.6;}tr td:first-child{width:6.25%;}.t{background:#ffeb3b;}.n{background:#1de9b6;}.w1 .a,.w0 .b{color:rgba(0,0,0,.26);background:#f5f5f5;}.w1 .a.t,.w0 .b.t{background:#fff9c4;}.w1 .a.n,.w0 .b.n{background:#a7ffeb;}@media only screen and (orientation:portrait){td:not(:first-child):not(.t):not(.n){display:none;}}@media (max-height:451px){body{font-size:10px;}}</style><?php ?>
<script>(function(m,n){function p(){var c=new Date(),f=c.getDay(),h=100*c.getHours()+c.getMinutes(),c=c.getTime(),a,e,g=0;if(s!==h){for(a=0;a<l.length;a+=1)l[a].className=t[a];if(1<=f&&5>=f){for(;g<m.length&&m[g]<=h;)g+=1;for(a=0;a<k[f].length;a+=1)for(e=0;e<k[f][a].length;e+=1)k[f][a][e].className+=g===a-1?" n":" t"}a=0;a:for(;2>a;a+=1)for(e=0;e<n[a].length;e++)if(1E3*(n[a][e]-172800)<c&&c<1E3*(n[a][e]+432E3)){document.body.className="w"+a;break a}s=h}u=setTimeout(p,1E3)}function w(){clearTimeout(u);document[q]||p()}function v(){var c=document.documentElement.clientWidth||document.body.clientWidth,f=document.documentElement.clientHeight||document.body.clientHeight,h=16;for(document.body.style.fontSize=h+"px";6<h&&(r.offsetWidth-1>c||r.offsetHeight-1>f);)h-=1,document.body.style.fontSize=h+"px"}var l,t=[],k=[],s,q,u,r;window.onload=function(){var c=document.getElementsByTagName("tr"),f=c.length,h=[],a=[],e=[],g,b,d;l=document.getElementsByTagName("td");for(b=0;b<l.length;b+=1)t[b]=l[b].className;for(b=1;5>=b;b+=1)for(k[b]=[],d=0;d<=m.length;d+=1)k[b][d]=[];for(b=0;b<f;b+=1)h[b]=c[b].getElementsByTagName("td"),a[b]=0,e[b]=1;for(b=1;5>=b;b+=1)for(;;){c=0;g=a[0];for(d=1;d<f;d+=1)a[d]<g&&(c=d,g=a[d]);if(g===12*b)break;g=h[c][e[c]];for(d=0;d<g.rowSpan;d+=1)a[c+d]+=g.colSpan,k[b][c+d].push(g);e[c]+=1}p();r=document.getElementsByTagName("table")[0];v();window.onresize=v;window.applicationCache&&applicationCache.addEventListener("updateready",function(e){applicationCache.swapCache();location.reload()},false);if("undefined"!==typeof document.hidden)q="hidden",f="visibilitychange";else if("undefined"!==typeof document.webkitHidden)q="webkitHidden",f="webkitvisibilitychange";else return;document.addEventListener(f,w,!1)}})(<?php
echo json_encode($timetable) . ',' . json_encode($week_js);
?>);</script><?php ?>
<table><?php
foreach ($table as $row) {
  echo '<tr>';
  foreach ($row as $cell) {
    $attrs = '';
    if ($cell['class'] != '') {
      $attrs .= ' class="' . $cell['class'] . '"';
    }
    if ($cell['rowspan'] > 1) {
      $attrs .= ' rowspan="' . $cell['rowspan'] . '"';
    }
    if ($cell['colspan'] > 1) {
      $attrs .= ' colspan="' . $cell['colspan'] . '"';
    }
    echo '<td' . $attrs . '>';
    echo implode('<br>', text_to_html($cell['text']));
  }
}
?></table><?php

  return ob_get_clean();
}


function text_to_html($arr) {
  return str_replace(array('&', '<'), array('&amp;', '&lt;'), $arr);
}
