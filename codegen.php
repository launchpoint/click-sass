<?

require_once('lib/sass/SassParser.class.php');

function compile_all_sass()
{
  global $manifests, $asset_folders;
  global $codegen;
  
  clear_cache(SASS_CACHE_FPATH);

  foreach($manifests as $module_name=>$manifest)
  {
    if (!$manifest['enabled']) continue;
    $path = $manifest['path']."/assets/sass";
    if (!file_exists($path)) continue;
    register_asset_folder('sass', $path, 'sass');
  }
  
  foreach($manifests as $module_name=>$manifest)
  {
    if (!array_key_exists($module_name, $asset_folders) || !array_key_exists('sass', $asset_folders[$module_name])) continue;
    
    foreach($asset_folders[$module_name]['sass'] as $path)
    {
      $vpath = ftov($path);
  
      $output_path = normalize_path(SASS_CACHE_FPATH . $vpath . "/../css");
      compile_sass_directory($path, $output_path);
      if (file_exists($output_path))
      {
        $codegen[] = "register_asset_folder('sass', '$output_path', 'css');";
      }
      $path .= "/browser";
      if (!file_exists($path)) continue;
      $output_path = normalize_path(SASS_CACHE_FPATH . $vpath . "/../css/browser");
      compile_sass_directory($path, $output_path);
    }
  }
}

function compile_sass_file($sass_src, $output_path)
{
  if (!file_exists($sass_src)) click_error("File $src does not exist for SASS processing.");
  $sass = eval_php($sass_src);
  $md5 = md5($sass);
  $src = SASS_CACHE_FPATH."/$md5.".basename($sass_src);

  if(file_exists($src)) return;
  $dst = $output_path."/".basename($src, '.sass').'.css';
  
  file_put_contents($src, $sass);

  $renderer = SassRenderer::EXPANDED;
  $parser = new SassParser(dirname($src), SASS_CACHE_FPATH , $renderer);
  
  // OUTPUT
  
  $css = $parser->fetch($src, $renderer);

  if (strlen(trim($css))==0) click_error("$src failed to output any CSS");
  
  $pfx = dirname(ftov($sass_src));
  $func = "
      if (!startswith(\$matches[1], '/'))
      {
        \$path = '$pfx/'.\$matches[1];
        return 'url('.\$path.')';
      } else {
        \$path = \$matches[1];
      }
      return 'url('.\$path.')';
    ";
  $css = preg_replace_callback("/url\\(['\"]?(.+?)['\"]?\\)/", create_function( '$matches', $func ), $css);
  
  ensure_writable_folder($output_path);
  if (!file_exists($output_path)) click_error("Expected output path $output_path for SASS processing.");
  file_put_contents($dst, $css);
}

function compile_sass_directory($input_path, $output_path)
{
  if (!file_exists($input_path)) click_error("Directory $path does not exist for SASS processing.");
  if (count(glob($input_path."/*.css"))>0) click_error("SASS directory $input_path contains .css files.");
  foreach (glob($input_path."/*.sass") as $filename) {
    compile_sass_file($filename, $output_path);
  }
}

compile_all_sass();
