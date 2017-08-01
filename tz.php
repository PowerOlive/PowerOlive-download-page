<?php

//ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
error_reporting(0); //抑制所有错误信息
@header("content-Type: text/html; charset=utf-8"); //语言强制
ob_start();
date_default_timezone_set('Asia/Shanghai');//此句用于消除时间差

define('HTTP_HOST', preg_replace('~^www\.~i', '', $_SERVER['HTTP_HOST']));

//修正 $_SERVER['REMOTE_ADDR']
if (isset($_SERVER["HTTP_X_REAL_IP"]))
{
  $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_X_REAL_IP"];
}
else if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
{
  $_SERVER['REMOTE_ADDR'] = preg_replace('/^.+,\s*/', '', $_SERVER["HTTP_X_FORWARDED_FOR"]);
}

$time_start = microtime_float();

function memory_usage()
{
  $memory  = ( ! function_exists('memory_get_usage')) ? '0' : round(memory_get_usage()/1024/1024, 2).'MB';
  return $memory;
}

// 计时
function microtime_float()
{
  $mtime = microtime();
  $mtime = explode(' ', $mtime);
  return $mtime[1] + $mtime[0];
}

//单位转换
function formatsize($size)
{
  $danwei=array(' B ',' K ',' M ',' G ',' T ');
  $allsize=array();
  $i=0;

  for($i = 0; $i <5; $i++)
  {
    if(floor($size/pow(1024,$i))==0){break;}
  }

  for($l = $i-1; $l >=0; $l--)
  {
    $allsize1[$l]=floor($size/pow(1024,$l));
    $allsize[$l]=$allsize1[$l]-$allsize1[$l+1]*1024;
  }

  $len=count($allsize);

  for($j = $len-1; $j >=0; $j--)
  {
    $fsize=$fsize.$allsize[$j].$danwei[$j];
  }
  return $fsize;
}

if ($_GET['act'] == "phpinfo")
{
  phpinfo();
  exit();
}

function get_dist_name()
{
    foreach (glob("/etc/*release") as $name) {
      if ($name == '/etc/centos-release' || $name == '/etc/redhat-release' || $name == '/etc/system-release') {
        return array_shift(file($name));
      }
      $release_info = @parse_ini_file($name);
      if (isset($release_info["DISTRIB_DESCRIPTION"]))
        return $release_info["DISTRIB_DESCRIPTION"];
      if (isset($release_info["PRETTY_NAME"]))
        return $release_info["PRETTY_NAME"];
    }

  return php_uname('s').' '.php_uname('r');
}

//linux系统探测
$sysInfo = sys_linux();

function sys_linux()
{
  // CPU
  if (false === ($str = @file("/proc/cpuinfo"))) return false;
  $str = implode("", $str);
  @preg_match_all("/processor\s{0,}\:+\s{0,}([\w\s\)\(\@.-]+)([\r\n]+)/s", $str, $processor);
  @preg_match_all("/model\s+name\s{0,}\:+\s{0,}([\w\s\)\(\@.-]+)([\r\n]+)/s", $str, $model);
  if (count($model[0]) == 0)
  {
    @preg_match_all("/Hardware\s{0,}\:+\s{0,}([\w\s\)\(\@.-]+)([\r\n]+)/s", $str, $model);
  }
  @preg_match_all("/cpu\s+MHz\s{0,}\:+\s{0,}([\d\.]+)[\r\n]+/", $str, $mhz);
  if (count($mhz[0]) == 0)
  {
    $values = @file("/sys/devices/system/cpu/cpu0/cpufreq/cpuinfo_max_freq");
    $mhz = array("", array(sprintf('%.3f', intval($values[0])/1000)));
  }
  @preg_match_all("/cache\s+size\s{0,}\:+\s{0,}([\d\.]+\s{0,}[A-Z]+[\r\n]+)/", $str, $cache);
  @preg_match_all("/(?i)bogomips\s{0,}\:+\s{0,}([\d\.]+)[\r\n]+/", $str, $bogomips);
  @preg_match_all("/(?i)(flags|Features)\s{0,}\:+\s{0,}(.+)[\r\n]+/", $str, $flags);
  if (false !== is_array($model[1]))
  {
    $res['cpu']['num'] = sizeof($processor[1]);
    if($res['cpu']['num']==1)
      $x1 = '';
    else
      $x1 = ' ×'.$res['cpu']['num'];
    $mhz[1][0] = ' | 频率:'.$mhz[1][0];
    if (count($cache[0]) > 0)
      $cache[1][0] = ' | 二级缓存:'.$cache[1][0];
    $bogomips[1][0] = ' | Bogomips:'.$bogomips[1][0];
    $res['cpu']['model'][] = $model[1][0].$mhz[1][0].$cache[1][0].$bogomips[1][0].$x1;
    $res['cpu']['flags'] = $flags[2][0];
    if (false !== is_array($res['cpu']['model'])) $res['cpu']['model'] = implode("<br>", $res['cpu']['model']);
    if (false !== is_array($res['cpu']['mhz'])) $res['cpu']['mhz'] = implode("<br>", $res['cpu']['mhz']);
    if (false !== is_array($res['cpu']['cache'])) $res['cpu']['cache'] = implode("<br>", $res['cpu']['cache']);
    if (false !== is_array($res['cpu']['bogomips'])) $res['cpu']['bogomips'] = implode("<br>", $res['cpu']['bogomips']);
  }

  // UPTIME
  if (false === ($str = @file("/proc/uptime"))) return false;
  $str = explode(" ", implode("", $str));
  $str = trim($str[0]);
  $min = $str / 60;
  $hours = $min / 60;
  $days = floor($hours / 24);
  $hours = floor($hours - ($days * 24));
  $min = floor($min - ($days * 60 * 24) - ($hours * 60));
  if ($days !== 0) $res['uptime'] = $days."天";
  if ($hours !== 0) $res['uptime'] .= $hours."小时";
  $res['uptime'] .= $min."分钟";

  // MEMORY
  if (false === ($str = @file("/proc/meminfo"))) return false;
  $str = implode("", $str);
  preg_match_all("/MemTotal\s{0,}\:+\s{0,}([\d\.]+).+?MemFree\s{0,}\:+\s{0,}([\d\.]+).+?Cached\s{0,}\:+\s{0,}([\d\.]+).+?SwapTotal\s{0,}\:+\s{0,}([\d\.]+).+?SwapFree\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buf);
  preg_match_all("/Buffers\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buffers);

  $res['memTotal'] = round($buf[1][0]/1024, 2);
  $res['memFree'] = round($buf[2][0]/1024, 2);
  $res['memBuffers'] = round($buffers[1][0]/1024, 2);
  $res['memCached'] = round($buf[3][0]/1024, 2);
  $res['memUsed'] = $res['memTotal']-$res['memFree'];
  $res['memPercent'] = (floatval($res['memTotal'])!=0)?round($res['memUsed']/$res['memTotal']*100,2):0;

  $res['memRealUsed'] = $res['memTotal'] - $res['memFree'] - $res['memCached'] - $res['memBuffers']; //真实内存使用
  $res['memRealFree'] = $res['memTotal'] - $res['memRealUsed']; //真实空闲
  $res['memRealPercent'] = (floatval($res['memTotal'])!=0)?round($res['memRealUsed']/$res['memTotal']*100,2):0; //真实内存使用率

  $res['memCachedPercent'] = (floatval($res['memCached'])!=0)?round($res['memCached']/$res['memTotal']*100,2):0; //Cached内存使用率

  $res['swapTotal'] = round($buf[4][0]/1024, 2);
  $res['swapFree'] = round($buf[5][0]/1024, 2);
  $res['swapUsed'] = round($res['swapTotal']-$res['swapFree'], 2);
  $res['swapPercent'] = (floatval($res['swapTotal'])!=0)?round($res['swapUsed']/$res['swapTotal']*100,2):0;

  // LOAD Board
  if (is_file("/sys/devices/virtual/dmi/id/board_name")) {
      $res['boardVendor'] = array_shift(file('/sys/devices/virtual/dmi/id/board_vendor'));
      $res['boardName'] = array_shift(file('/sys/devices/virtual/dmi/id/board_name'));
      $res['boardVersion'] = array_shift(file('/sys/devices/virtual/dmi/id/board_version'));
  } else if (is_file("/sys/devices/virtual/android_usb/android0/f_rndis/manufacturer")) {
      $res['boardVendor'] = array_shift(file('/sys/devices/virtual/android_usb/android0/f_rndis/manufacturer'));
      $res['boardName'] = '';
      $res['boardVersion'] = '';
  }

  // LOAD BIOS
  if (is_file("/sys/devices/virtual/dmi/id/bios_vendor")) {
      $res['BIOSVendor'] = array_shift(file('/sys/devices/virtual/dmi/id/bios_vendor'));
      $res['BIOSVersion'] = array_shift(file('/sys/devices/virtual/dmi/id/bios_version'));
      $res['BIOSDate'] = array_shift(file('/sys/devices/virtual/dmi/id/bios_date'));
  } else if (is_file("/sys/devices/virtual/android_usb/android0/iProduct")) {
      $res['BIOSVendor'] = array_shift(file('/sys/devices/virtual/android_usb/android0/iManufacturer'));
      $res['BIOSVersion'] = array_shift(file('/sys/devices/virtual/android_usb/android0/iProduct'));
      $res['BIOSDate'] = '';
  }

  // LOAD DISK
  if ($dirs=glob("/sys/class/block/s*")) {
      $res['diskModel'] = array_shift(file($dirs[0]."/device/model"));
      $res['diskVendor'] = array_shift(file($dirs[0]."/device/vendor"));
  } else if ($dirs=glob("/sys/class/block/mmc*")) {
      $res['diskModel'] = array_shift(file($dirs[0]."/device/name"));
      $res['diskVendor'] = array_shift(file($dirs[0]."/device/type"));
  }

  // LOAD AVG
  if (false === ($str = @file("/proc/loadavg"))) return false;
  $str = explode(" ", implode("", $str));
  $str = array_chunk($str, 4);
  $res['loadAvg'] = implode(" ", $str[0]);

  return $res;
}

$uptime = $sysInfo['uptime']; //在线时间
$stime = date('Y-m-d H:i:s'); //系统当前时间

//硬盘
$dt = round(@disk_total_space(".")/(1024*1024*1024),3); //总
$df = round(@disk_free_space(".")/(1024*1024*1024),3); //可用
$du = $dt-$df; //已用
$hdPercent = (floatval($dt)!=0)?round($du/$dt*100,2):0;

$load = $sysInfo['loadAvg'];  //系统负载


//判断内存如果小于1G，就显示M，否则显示G单位
if($sysInfo['memTotal']<1024)
{
  $memTotal = $sysInfo['memTotal']." M";
  $mt = $sysInfo['memTotal']." M";
  $mu = $sysInfo['memUsed']." M";
  $mf = $sysInfo['memFree']." M";
  $mc = $sysInfo['memCached']." M"; //cache化内存
  $mb = $sysInfo['memBuffers']." M";  //缓冲
  $st = $sysInfo['swapTotal']." M";
  $su = $sysInfo['swapUsed']." M";
  $sf = $sysInfo['swapFree']." M";
  $swapPercent = $sysInfo['swapPercent'];
  $memRealUsed = $sysInfo['memRealUsed']." M"; //真实内存使用
  $memRealFree = $sysInfo['memRealFree']." M"; //真实内存空闲
  $memRealPercent = $sysInfo['memRealPercent']; //真实内存使用比率
  $memPercent = $sysInfo['memPercent']; //内存总使用率
  $memCachedPercent = $sysInfo['memCachedPercent']; //cache内存使用率
}
else
{
  $memTotal = round($sysInfo['memTotal']/1024,3)." G";
  $mt = round($sysInfo['memTotal']/1024,3)." G";
  $mu = round($sysInfo['memUsed']/1024,3)." G";
  $mf = round($sysInfo['memFree']/1024,3)." G";
  $mc = round($sysInfo['memCached']/1024,3)." G";
  $mb = round($sysInfo['memBuffers']/1024,3)." G";
  $st = round($sysInfo['swapTotal']/1024,3)." G";
  $su = round($sysInfo['swapUsed']/1024,3)." G";
  $sf = round($sysInfo['swapFree']/1024,3)." G";
  $swapPercent = $sysInfo['swapPercent'];
  $memRealUsed = round($sysInfo['memRealUsed']/1024,3)." G"; //真实内存使用
  $memRealFree = round($sysInfo['memRealFree']/1024,3)." G"; //真实内存空闲
  $memRealPercent = $sysInfo['memRealPercent']; //真实内存使用比率
  $memPercent = $sysInfo['memPercent']; //内存总使用率
  $memCachedPercent = $sysInfo['memCachedPercent']; //cache内存使用率
}

//网卡流量
$strs = @file("/proc/net/dev");

for ($i = 2; $i < count($strs); $i++ )
{
  preg_match_all( "/([^\s]+):[\s]{0,}(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/", $strs[$i], $info );
  $NetOutSpeed[$i] = $info[10][0];
  $NetInputSpeed[$i] = $info[2][0];
  $NetInput[$i] = formatsize($info[2][0]);
  $NetOut[$i]  = formatsize($info[10][0]);
}

//ajax调用实时刷新
if ($_GET['act'] == "rt")
{
  $arr=array('useSpace'=>"$du",
             'freeSpace'=>"$df",
             'hdPercent'=>"$hdPercent",
             'barhdPercent'=>"$hdPercent%",
             'TotalMemory'=>"$mt",
             'UsedMemory'=>"$mu",
             'FreeMemory'=>"$mf",
             'CachedMemory'=>"$mc",
             'Buffers'=>"$mb",
             'TotalSwap'=>"$st",
             'swapUsed'=>"$su",
             'swapFree'=>"$sf",
             'loadAvg'=>"$load",
             'uptime'=>"$uptime",
             'freetime'=>"$freetime",
             'bjtime'=>"$bjtime",
             'stime'=>"$stime",
             'memRealPercent'=>"$memRealPercent",
             'memRealUsed'=>"$memRealUsed",
             'memRealFree'=>"$memRealFree",
             'memPercent'=>"$memPercent%",
             'memCachedPercent'=>"$memCachedPercent",
             'barmemCachedPercent'=>"$memCachedPercent%",
             'swapPercent'=>"$swapPercent",
             'barmemRealPercent'=>"$memRealPercent%",
             'barswapPercent'=>"$swapPercent%",
             'NetOut2'=>"$NetOut[2]",
             'NetOut3'=>"$NetOut[3]",
             'NetOut4'=>"$NetOut[4]",
             'NetOut5'=>"$NetOut[5]",
             'NetOut6'=>"$NetOut[6]",
             'NetOut7'=>"$NetOut[7]",
             'NetOut8'=>"$NetOut[8]",
             'NetOut9'=>"$NetOut[9]",
             'NetOut10'=>"$NetOut[10]",
             'NetInput2'=>"$NetInput[2]",
             'NetInput3'=>"$NetInput[3]",
             'NetInput4'=>"$NetInput[4]",
             'NetInput5'=>"$NetInput[5]",
             'NetInput6'=>"$NetInput[6]",
             'NetInput7'=>"$NetInput[7]",
             'NetInput8'=>"$NetInput[8]",
             'NetInput9'=>"$NetInput[9]",
             'NetInput10'=>"$NetInput[10]",
             'NetOutSpeed2'=>"$NetOutSpeed[2]",
             'NetOutSpeed3'=>"$NetOutSpeed[3]",
             'NetOutSpeed4'=>"$NetOutSpeed[4]",
             'NetOutSpeed5'=>"$NetOutSpeed[5]",
             'NetInputSpeed2'=>"$NetInputSpeed[2]",
             'NetInputSpeed3'=>"$NetInputSpeed[3]",
             'NetInputSpeed4'=>"$NetInputSpeed[4]",
             'NetInputSpeed5'=>"$NetInputSpeed[5]");
  $jarr=json_encode($arr);
  $_GET['callback'] = htmlspecialchars($_GET['callback']);
  echo $_GET['callback'],'(',$jarr,')';
  exit;
}

//ajax调用计算CPU使用率
if ($_GET['act'] == "cpu")
{
  $duration = 1;

  $stat1=array_slice(preg_split('/\s+/', trim(array_shift(file('/proc/stat')))), 1);
  sleep($duration);
  $stat2=array_slice(preg_split('/\s+/', trim(array_shift(file('/proc/stat')))), 1);

  $diff=array_map(function ($x,$y) {return intval($y)-intval($x);}, $stat1, $stat2);
  $total=array_sum($diff)/100;

  $cpu=array();
  $cpu['user'] = $diff[0]/$total;
  $cpu['nice'] = $diff[1]/$total;
  $cpu['sys'] = $diff[2]/$total;
  $cpu['idle'] = $diff[3]/$total;
  $cpu['iowait'] = $diff[4]/$total;
  $cpu['irq'] = $diff[5]/$total;
  $cpu['softirq'] = $diff[6]/$total;
  $cpu['steal'] = $diff[7]/$total;

  $jarr=json_encode($cpu);
  $_GET['callback'] = htmlspecialchars($_GET['callback']);
  echo $_GET['callback'],'(',$jarr,')';
  exit;
}

//调用ipip.net取得IP位置
if ($_GET['act'] == "iploc")
{
  $ip = $_SERVER['REMOTE_ADDR'];

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://www.ipip.net/ip.html");
  curl_setopt($ch, CURLOPT_REFERER, "https://www.ipip.net/");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERAGENT, "curl/7.47.0");
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, "ip=".$ip);
  $result = curl_exec($ch);
  curl_close($ch);

  $jarr = array();
  if (preg_match("/<span id=\"myself\">\s*(.+?)\s*</", $result, $matches))
  {
    array_push($jarr, $matches[1]);
  }
  preg_match_all('/<div style=".*?color:red;.*?">(.+?)<\/div>/', $result, $matches);
  if (count($matches) > 1)
  {
    array_push($jarr, preg_replace('/\s+/', '', end($matches[1])));
  }

  $_GET['callback'] = htmlspecialchars($_GET['callback']);
  echo $_GET['callback'],'(',json_encode($jarr),')';
  exit;
}

?>
<!DOCTYPE html>
<meta charset="utf-8">
<title><?php echo $_SERVER['SERVER_NAME']; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<style>
body {margin:0;font-family:Tahoma,"Microsoft Yahei",Arial,Serif;}
.container{padding-right:15px;padding-left:15px;margin-right:auto;margin-left:auto;}
@media(min-width:768px){.container{max-width:750px;}}
@media(min-width:992px){.container{max-width:970px;}}
@media(min-width:1200px){.container{max-width:1170px;}}
</style>

<a id="w_top"></a>

<div class="container">
<table>
  <tr>
    <th><a href="?act=phpinfo">PHP Info</a></th>
    <th><a href="/files/">文件下载</a></th>
    <th><a href="//gateway.<?php echo $_SERVER['HTTP_HOST'];?>">网关管理</a></th>
    <th><a href="//grafana.<?php echo $_SERVER['HTTP_HOST'];?>/dashboard/db/system-overview">性能监控</a></th>
  </tr>
</table>

<!--服务器相关参数-->
<table>
  <tr><th colspan="4">服务器参数</th></tr>
  <tr>
    <td>服务器域名/IP 地址</td>
    <td colspan="3"><?php echo @get_current_user();?> - <?php echo $_SERVER['SERVER_NAME'];?>(<?php echo @gethostbyname($_SERVER['SERVER_NAME']); ?>)&nbsp;&nbsp;你的 IP 地址是：<?php echo @$_SERVER['REMOTE_ADDR'];?> (<span id="iploc">未知位置</span>)</td>
  </tr>
  <tr>
    <td>服务器标识</td>
    <td colspan="3"><?php if($sysInfo['win_n'] != ''){echo $sysInfo['win_n'];}else{echo @php_uname();};?></td>
  </tr>
  <tr>
    <td>服务器操作系统</td>
    <td><?php echo @get_dist_name(); ?> &nbsp;内核版本：<?php if('/'==DIRECTORY_SEPARATOR){$os = explode(' ',php_uname()); echo $os[2];}else{echo $os[1];} ?></td>
    <td>服务器解译引擎</td>
    <td><?php echo $_SERVER['SERVER_SOFTWARE'];?></td>
  </tr>
  <tr>
    <td>服务器语言</td>
    <td><?php $lc_ctype = setlocale(LC_CTYPE,0); echo $lc_ctype=='C'?'POSIX':$lc_ctype;?></td>
    <td>服务器端口</td>
    <td><?php echo $_SERVER['SERVER_PORT'];?></td>
  </tr>
  <tr>
    <td>服务器主机名</td>
    <td><?php if('/'==DIRECTORY_SEPARATOR ){echo $os[1];}else{echo $os[2];} ?></td>
    <td>管理员邮箱</td>
    <td><?php echo $_SERVER['SERVER_ADMIN'];?></td>
  </tr>
  <tr>
    <td>探针路径</td>
    <td colspan="3"><?php echo str_replace('\\','/',__FILE__)?str_replace('\\','/',__FILE__):$_SERVER['SCRIPT_FILENAME'];?></td>
  </tr>
</table>

<table>
  <tr><th colspan="4">服务器实时数据</th></tr>
  <tr>
    <td>服务器当前时间</td>
    <td><span id="stime"><?php echo $stime;?></span></td>
    <td>服务器已运行时间</td>
    <td><span id="uptime"><?php echo $uptime;?></span></td>
  </tr>
  <tr>
    <td>CPU 型号 [<?php echo $sysInfo['cpu']['num'];?>核]</td>
    <td colspan="3"><?php echo $sysInfo['cpu']['model'];?></td>
  </tr>
  <tr>
    <td>CPU 指令集</td>
    <td colspan="3" style="word-wrap: break-word;width: 64em;"><?php echo $sysInfo['cpu']['flags'];?></td>
  </tr>
<?php if (isset($sysInfo['boardVendor'])) : ?>
  <tr>
    <td>主板型号</td>
    <td><?php echo $sysInfo['boardVendor'] . " " . $sysInfo['boardName'] . " " . $sysInfo['boardVersion'];?></td>
    <td>主板 BIOS</td>
    <td><?php echo $sysInfo['BIOSVendor'] . " " . $sysInfo['BIOSVersion'] . " " . $sysInfo['BIOSDate'];?></td>
  </tr>
<?php endif; ?>
<?php if (isset($sysInfo['diskModel'])) : ?>
  <tr>
    <td>硬盘型号</td>
    <td colspan="3"><?php echo $sysInfo['diskModel'] . " " . $sysInfo['diskVendor'];?></td>
  </tr>
<?php endif; ?>
  <tr>
    <td>CPU 使用状况</td>
    <td colspan="3">
      <span id="cpuUSER" class="text-info">0.0</span> user,
      <span id="cpuSYS" class="text-info">0.0</span> sys,
      <span id="cpuNICE">0.0</span> nice,
      <span id="cpuIDLE" class="text-info">99.9</span> idle,
      <span id="cpuIOWAIT">0.0</span> iowait,
      <span id="cpuIRQ">0.0</span> irq,
      <span id="cpuSOFTIRQ">0.0</span> softirq,
      <span id="cpuSTEAL">0.0</span> steal
      <div class="progress"><div id="barcpuPercent" class="progress-bar progress-bar-success" role="progressbar" style="width:1px" >&nbsp;</div></div>
    </td>
  </tr>
  <tr>
    <td>内存使用状况</td>
    <td colspan="3">
<?php
$tmp = array(
    'memTotal', 'memUsed', 'memFree', 'memPercent',
    'memCached', 'memRealPercent',
    'swapTotal', 'swapUsed', 'swapFree', 'swapPercent'
);
foreach ($tmp AS $v) {
    $sysInfo[$v] = $sysInfo[$v] ? $sysInfo[$v] : 0;
}
?>
          物理内存：共
          <span id="TotalMemory" class="text-info"><?php echo $memTotal;?> </span>
           , 已用
          <span id="UsedMemory" class="text-info"><?php echo $mu;?></span>
          , 空闲
          <span id="FreeMemory" class="text-info"><?php echo $mf;?></span>
          , 使用率
          <span id="memPercent"><?php echo $memPercent;?></span>
          <div class="progress"><div id="barmemPercent" class="progress-bar progress-bar-success" role="progressbar" style="width:<?php echo $memPercent?>%" ></div></div>
<?php
//判断如果cache为0，不显示
if($sysInfo['memCached']>0)
{
?>
      Cache 化内存为 <span id="CachedMemory"><?php echo $mc;?></span>
      , 使用率
          <span id="memCachedPercent"><?php echo $memCachedPercent;?></span>%
       | Buffers 缓冲为  <span id="Buffers"><?php echo $mb;?></span>
          <div class="progress"><div id="barmemCachedPercent" class="progress-bar progress-bar-info" role="progressbar" style="width:<?php echo $memCachedPercent?>%" ></div></div>
          真实内存使用
          <span id="memRealUsed"><?php echo $memRealUsed;?></span>
      , 真实内存空闲
          <span id="memRealFree"><?php echo $memRealFree;?></span>
      , 使用率
          <span id="memRealPercent"><?php echo $memRealPercent;?></span>%
          <div class="progress"><div id="barmemRealPercent" class="progress-bar progress-bar-warning" role="progressbar" style="width:<?php echo $memRealPercent?>%" ></div></div>
<?php
}
//判断如果 SWAP 区为0，不显示
if($sysInfo['swapTotal']>0)
{
?>
          SWAP 区：共
          <span id="TotalSwap"><?php echo $st;?></span>
          , 已使用
          <span id="swapUsed"><?php echo $su;?></span>
          , 空闲
          <span id="swapFree"><?php echo $sf;?></span>
          , 使用率
          <span id="swapPercent"><?php echo $swapPercent;?></span>%
          <div class="progress"><div id="barswapPercent" class="progress-bar progress-bar-danger" role="progressbar" style="width:<?php echo $swapPercent?>%" ></div> </div>

<?php
}
?>
    </td>
  </tr>
  <tr>
    <td>硬盘使用状况</td>
    <td colspan="3">
    总空间 <?php echo $dt;?>&nbsp;G，
    已用 <span id="useSpace"><?php echo $du;?></span>&nbsp;G，
    空闲 <span id="freeSpace"><?php echo $df;?></span>&nbsp;G，
    使用率 <span id="hdPercent"><?php echo $hdPercent;?></span>%
    <div class="progress"><div id="barhdPercent" class="progress-bar progress-bar-black" role="progressbar" style="width:<?php echo $hdPercent?>%" ></div> </div>
    </td>
  </tr>
  <tr>
    <td>系统平均负载</td>
    <td colspan="3" class="text-danger"><span id="loadAvg"><?php echo $load;?></span></td>
  </tr>
</table>

<?php if (false !== ($strs = @file("/proc/net/dev"))) : ?>
<table class="table table-striped table-bordered table-hover table-condensed">
    <tr><th colspan="5">网络使用状况</th></tr>
<?php for ($i = 2; $i < count($strs); $i++ ) : ?>
<?php preg_match_all( "/([^\s]+):[\s]{0,}(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/", $strs[$i], $info );?>
  <tr>
    <td style="width:13%"><?php echo $info[1][0]?> : </td>
    <td style="width:29%">入网: <span class="text-info" id="NetInput<?php echo $i?>"><?php echo $NetInput[$i]?></span></td>
    <td style="width:14%">实时: <span class="text-info" id="NetInputSpeed<?php echo $i?>">0B/s</span></td>
    <td style="width:29%">出网: <span class="text-info" id="NetOut<?php echo $i?>"><?php echo $NetOut[$i]?></span></td>
    <td style="width:14%">实时: <span class="text-info" id="NetOutSpeed<?php echo $i?>">0B/s</span></td>
  </tr>
<?php endfor; ?>
</table>
<?php endif; ?>

<?php if (0 < count($strs = array_splice(@file("/proc/net/arp"), 1))) : ?>
<table class="table table-striped table-bordered table-hover table-condensed">
    <tr>
      <th colspan="4">网络邻居</th>
    </tr>
<?php $seen = array(); ?>
<?php for ($i = 0; $i < count($strs); $i++ ) : ?>
<?php $info = preg_split('/\s+/', $strs[$i]); ?>
<?php if ('0x2' == $info[2] && !isset($seen[$info[3]])) : ?>
<?php $seen[$info[3]] = true; ?>
    <tr>
        <td><?php echo $info[0];?> </td>
        <td>MAC: <span class="text-info"><?php  echo $info[3];?></span></td>
        <td>类型: <span class="text-info"><?php echo $info[1]=='0x1'?'ether':$info[1];?></span></td>
        <td>接口: <span class="text-info"><?php echo $info[5];?></span></td>
    </tr>
<?php endif; ?>
<?php endfor; ?>
</table>
<?php endif; ?>

<table class="table table-striped table-bordered table-hover table-condensed">
  <tr>
    <td>PHP探针(<a href="https://phuslu.github.io">雅黑修改版</a>) v1.0</td>
    <td><?php $run_time = sprintf('%0.4f', microtime_float() - $time_start);?>Processed in <?php echo $run_time?> seconds. <?php echo memory_usage();?> memory usage.</td>
    <td><a href="#w_top">返回顶部</a></td>
  </tr>
</table>

</div>

<style>
table {
	width: 100%;
	max-width: 100%;
	margin-bottom: 20px;
	border: 1px solid #ddd;
	padding: 0;
	border-collapse: collapse;
}
table th {
	font-size: 14px;
}
table tr {
	border: 1px solid #ddd;
	padding: 5px;
}
table tr:nth-child(odd) {
	background: #f9f9f9
}
table th, table td {
	border: 1px solid #ddd;
	font-size: 14px;
	line-height: 20px;
	padding: 3px;
	text-align: left;
}
table.table-hover > tbody > tr:hover > td,
table.table-hover > tbody > tr:hover > th {
	background-color: #f5f5f5;
}
a {
	color: #337ab7;
	text-decoration: none;
}
a:hover, a:focus {
	color: #2a6496;
	text-decoration: underline;
}
.text-info {
	color: #3a87ad;
}
.text-danger {
	color: #b94a48;
}
.progress {
	height:10px;
	width:90%;
	margin-bottom: 20px;
	overflow: hidden;
	background-color: #f5f5f5;
	border-radius: 4px;
	box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
}
.progress-bar {
	float: left;
	width: 0;
	height: 100%;
	font-size: 12px;
	color: #ffffff;
	text-align: center;
	background-color: #428bca;
	box-shadow: inset 0 -1px 0 rgba(0, 0, 0, 0.15);
	transition: width 0.6s ease;
	background-size: 40px 40px;
}
.progress-bar-success {
	background-color: #5cb85c;
}
.progress-bar-info {
	background-color: #5bc0de;
}
.progress-bar-warning {
	background-color: #f0ad4e;
}
.progress-bar-danger {
	background-color: #d9534f;
}
.progress-bar-black {
	background-color: #333;
}
</style>

<script>
var cdom = {
    element: null,
    get: function (o) {
      var obj = Object.create(this);
      obj.element = (typeof o == "object") ? o : document.createElement(o);
      return obj;
    },
    width: function (w) {
      if (!this.element)
        return;
      this.element.style.width = w;
      return this;
    },
    removeClass: function(c) {
      if (!this.element)
        return;
      var el = this.element;
      if (typeof c == "undefined")
        el.className = '';
      else if (el.classList)
        el.classList.remove(c);
      else
        el.className = el.className.replace(new RegExp('(^|\\b)' + c.split(' ').join('|') + '(\\b|$)', 'gi'), ' ');
      return this;
    },
    addClass: function(c) {
      if (!this.element)
        return;
      var el = this.element;
      if (el.classList)
        el.classList.add(c);
      else
        el.className += ' ' + c;
      return this;
    },
    html: function (h) {
      if (!this.element)
        return;
      this.element.innerHTML = h;
      return this;
    }
};

$ = function(s) {
  if (s[0] == '#')
    return cdom.get(document.getElementById(s.substring(1)));
  else
    return cdom.get(document.querySelector(s));
};

$.getJSON = function (url, f) {
  var xhr = new XMLHttpRequest();
  xhr.open('GET', url + '&_=' + new Date().getTime(), true);
  xhr.onload = function() {
    if (xhr.status >= 200 && xhr.status < 400) {
      var data = JSON.parse(xhr.responseText.replace(/^.*?\(/, '').replace(/\).*?$/, ''));
      f(data);
    }
  };
  xhr.send();
}

document.addEventListener('DOMContentLoaded', function(){
   getData();
   setInterval(getData, 1000);
});

function getData() {
  $.getJSON('?act=rt&callback=?', displayData);
}

var OutSpeed2=<?php echo floor($NetOutSpeed[2]) ?>;
var OutSpeed3=<?php echo floor($NetOutSpeed[3]) ?>;
var OutSpeed4=<?php echo floor($NetOutSpeed[4]) ?>;
var OutSpeed5=<?php echo floor($NetOutSpeed[5]) ?>;
var InputSpeed2=<?php echo floor($NetInputSpeed[2]) ?>;
var InputSpeed3=<?php echo floor($NetInputSpeed[3]) ?>;
var InputSpeed4=<?php echo floor($NetInputSpeed[4]) ?>;
var InputSpeed5=<?php echo floor($NetInputSpeed[5]) ?>;

function ForDight(Dight,How)
{
  if (Dight<0){
    var Last=0+"B/s";
  }else if (Dight<1024){
    var Last=Math.round(Dight*Math.pow(10,How))/Math.pow(10,How)+"B/s";
  }else if (Dight<1048576){
    Dight=Dight/1024;
    var Last=Math.round(Dight*Math.pow(10,How))/Math.pow(10,How)+"K/s";
  }else{
    Dight=Dight/1048576;
    var Last=Math.round(Dight*Math.pow(10,How))/Math.pow(10,How)+"M/s";
  }
  return Last;
}
function displayData(data)
{
  $("#useSpace").html(data.useSpace);
  $("#freeSpace").html(data.freeSpace);
  $("#hdPercent").html(data.hdPercent);
  $("#barhdPercent").width(data.barhdPercent);
  $("#TotalMemory").html(data.TotalMemory);
  $("#UsedMemory").html(data.UsedMemory);
  $("#FreeMemory").html(data.FreeMemory);
  $("#CachedMemory").html(data.CachedMemory);
  $("#Buffers").html(data.Buffers);
  $("#TotalSwap").html(data.TotalSwap);
  $("#swapUsed").html(data.swapUsed);
  $("#swapFree").html(data.swapFree);
  $("#swapPercent").html(data.swapPercent);
  $("#loadAvg").html(data.loadAvg);
  $("#uptime").html(data.uptime);
  $("#freetime").html(data.freetime);
  $("#stime").html(data.stime);
  $("#bjtime").html(data.bjtime);
  $("#memRealUsed").html(data.memRealUsed);
  $("#memRealFree").html(data.memRealFree);
  $("#memRealPercent").html(data.memRealPercent);
  $("#memPercent").html(data.memPercent);
  $("#barmemPercent").width(data.memPercent);
  $("#barmemRealPercent").width(data.barmemRealPercent);
  $("#memCachedPercent").html(data.memCachedPercent);
  $("#barmemCachedPercent").width(data.barmemCachedPercent);
  $("#barswapPercent").width(data.barswapPercent);
  $("#NetOut2").html(data.NetOut2);
  $("#NetOut3").html(data.NetOut3);
  $("#NetOut4").html(data.NetOut4);
  $("#NetOut5").html(data.NetOut5);
  $("#NetOut6").html(data.NetOut6);
  $("#NetOut7").html(data.NetOut7);
  $("#NetOut8").html(data.NetOut8);
  $("#NetOut9").html(data.NetOut9);
  $("#NetOut10").html(data.NetOut10);
  $("#NetInput2").html(data.NetInput2);
  $("#NetInput3").html(data.NetInput3);
  $("#NetInput4").html(data.NetInput4);
  $("#NetInput5").html(data.NetInput5);
  $("#NetInput6").html(data.NetInput6);
  $("#NetInput7").html(data.NetInput7);
  $("#NetInput8").html(data.NetInput8);
  $("#NetInput9").html(data.NetInput10);
  $("#NetOutSpeed2").html(ForDight((data.NetOutSpeed2-OutSpeed2),3)); OutSpeed2=data.NetOutSpeed2;
  $("#NetOutSpeed3").html(ForDight((data.NetOutSpeed3-OutSpeed3),3)); OutSpeed3=data.NetOutSpeed3;
  $("#NetOutSpeed4").html(ForDight((data.NetOutSpeed4-OutSpeed4),3)); OutSpeed4=data.NetOutSpeed4;
  $("#NetOutSpeed5").html(ForDight((data.NetOutSpeed5-OutSpeed5),3)); OutSpeed5=data.NetOutSpeed5;
  $("#NetInputSpeed2").html(ForDight((data.NetInputSpeed2-InputSpeed2),3)); InputSpeed2=data.NetInputSpeed2;
  $("#NetInputSpeed3").html(ForDight((data.NetInputSpeed3-InputSpeed3),3)); InputSpeed3=data.NetInputSpeed3;
  $("#NetInputSpeed4").html(ForDight((data.NetInputSpeed4-InputSpeed4),3)); InputSpeed4=data.NetInputSpeed4;
  $("#NetInputSpeed5").html(ForDight((data.NetInputSpeed5-InputSpeed5),3)); InputSpeed5=data.NetInputSpeed5;
}

document.addEventListener('DOMContentLoaded', function(){
  getCPUData();
  setInterval(getCPUData, 2000);
});

function getCPUData()
{
  $.getJSON('?act=cpu&callback=?', function (data) {
    $("#cpuUSER").html(data.user.toFixed(1));
    $("#cpuSYS").html(data.sys.toFixed(1));
    $("#cpuNICE").html(data.nice.toFixed(1));
    $("#cpuIDLE").html(data.idle.toFixed(1).substring(0,4));
    $("#cpuIOWAIT").html(data.iowait.toFixed(1));
    $("#cpuIRQ").html(data.irq.toFixed(1));
    $("#cpuSOFTIRQ").html(data.softirq.toFixed(1));
    $("#cpuSTEAL").html(data.steal.toFixed(1));

    usage = 100 - (data.idle+data.iowait);
    if (usage > 75)
      $("#barcpuPercent").width(usage+'%').removeClass().addClass('progress-bar-danger');
    else if (usage > 50)
      $("#barcpuPercent").width(usage+'%').removeClass().addClass('progress-bar-warning');
    else if (usage > 25)
      $("#barcpuPercent").width(usage+'%').removeClass().addClass('progress-bar-info');
    else
      $("#barcpuPercent").width(usage+'%').removeClass().addClass('progress-bar-success');
  });
}

document.addEventListener('DOMContentLoaded', function(){
  $.getJSON('?act=iploc&callback=?', function (data) {
    if (data[1] != null && data[1].substring(0,4) == data[0].substring(0,4)) {
      $("#iploc").html(data[1] + data[0].replace(/^\S+/, ''));
    } else {
      $("#iploc").html(data[0]);
    }
  });
});
</script>

