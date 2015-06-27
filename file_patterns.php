<?php


$nametemplates=[
  array(
    "matchtype" => 'extension',
    "pattern" => "jpg",
    "type" => 'picture',
    "template" => '%{file[destbasedir]}%{s}%{04dto[year]}%{s}%{02dto[month]}%{s}%{02dto[day]}%{s}%{04dto[year]}-%{02dto[month]}-%{02dto[day]} %{02dto[hour]}_%{02dto[minute]}_%{02dto[second]} utc (%{isize})-000.%{ext}',
    "metaprovider" => 'exiftool',
    "metamap" => array(
      "dto" => array(
          'path'=>"Composite,DateTimeOriginal",
          'operation'=>'dparse'
      ),
      "isize" => array (
          'path'=>"Composite,ImageSize",
      )
    )
  ),
  array(
    "matchtype" => 'extension',
    "pattern" => "mts",
    "type" => 'video',
    "template" => '%{file[destbasedir]}%{s}%{04dto[year]}%{s}%{02dto[month]}%{s}%{02dto[day]}%{s}%{04dto[year]}-%{02dto[month]}-%{02dto[day]} %{02dto[hour]}_%{02dto[minute]}_%{02dto[second]} utc-0.%{ext}',
    "metaprovider" => 'exiftool',
    "metamap" => array(
      "dto" => array(
          'path'=>"H264,DateTimeOriginal",
          'operation'=>'dparse'
      ),
      "isize" => array (
          'path'=>"Composite,ImageSize",
      )
    ),
    #"destdir"=>DESTDIR
  ),
  array(
    "matchtype" => 'extension',
    "pattern" => "avi",
    "type" => 'video',
    "template" => '%{file[destbasedir]}%{s}%{04dto[year]}%{s}%{02dto[month]}%{s}%{02dto[day]}%{s}%{04dto[year]}-%{02dto[month]}-%{02dto[day]} %{02dto[hour]}_%{02dto[minute]}_%{02dto[second]} utc (%{isize})-0.%{ext}',
    "metaprovider" => 'exiftool',
    "metamap" => array(
      "dto" => array(
          'path'=>"RIFF,DateTimeOriginal",
          'operation'=>'dparse'
      ),
      "isize" => array (
          'path'=>"Composite,ImageSize",
      )
    ),
    #"destdir"=>DESTDIR
  )
];

?>
