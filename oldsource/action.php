<?php
/**
 * DokuWiki Plugin filespace (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Oliver Geisen <oliver.geisen@kreisbote.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'action.php';

class action_plugin_filespace extends DokuWiki_Action_Plugin {

  /**
   * Runtime vars
   */
  var $_cache = array(
    'ns' => array(),
  );


  /**
   * Register its handlers with the dokuwiki's event controller
   */
  public function register(Doku_Event_Handler &$controller)
  {
       $controller->register_hook('IO_WIKIPAGE_WRITE', 'AFTER', $this, 'handle_io_wikipage_write');
  }


  /**
   * Handle the event
   */
  public function handle_io_wikipage_write(Doku_Event &$event, $param)
  {
    global $conf;
    global $INFO;
    global $ID;

    # puts PHP errormessages in superglobal $php_errormsg
    ini_set('track_errors',true);

    // get current namespace (colon-separated)
    $ns = $event->data[1];

    // handle only namespace start-pages (except the toplevel one)
    $pn = $event->data[2];
    $is_attic = $event->data[3];
    if (($pn != $conf['start']) || ($ns == '') || $is_attic)
    {
      return;
    }

    // get current title of page
    if (preg_match('/==+(.+?)==+/',$event->data[0][1],$match))
    {
      $title = trim($match[1]);
    }

    // get title of last revision of page (if any) to find changes
    $last_rev = $INFO['meta']['last_change']['date'];
    if ($last_rev)
    {
      $wiki = rawWiki($ID,$last_rev);
      if (preg_match('/==+(.+?)==+/',$wiki,$match))
      {
	$prev_title = trim($match[1]);
      }
    }

    // var is true if page was erased
    $page_empty = ($event->data[0][1] == '');

    //
    // ACTION
    //
    if ($page_empty)
    {
      $this->_debug('Erase filespace');
      $this->_remove_fs($ns);
    }
    elseif ($title && ! $prev_title)
    {
      $this->_debug('Create filespace');
      $this->_create_fs($ns);
    }
    elseif ($title && $prev_title && $title == $prev_title)
    {
      $this->_debug('No change. Repair filespace');
      $this->_create_fs($ns); # repair fs, if needed
    }
    elseif ($title && $prev_title && $title != $prev_title)
    {
      $this->_debug('Rename filespace');
      $this->_rename_fs($ns,$prev_title);
    }
    else
    {
      $this->_debug('** UNKNOWN ACTION **');
    }

    return;
  }

  #----------------------------------------------------------------------------#

  /**
   *
   */
  private function _create_fs($ns)
  {
    $fs = $this->_fs($ns);
    if ( ! $fs)
    {
      return;
    }

    if (! @file_exists($fs))
    {
      if (! @mkdir($fs))
      {
	msg('Der Filespace für diese Seite konnte nicht erstellt werden: "'.$fs.'" (Die Fehlermeldung lautet: "'.$php_errormsg.'")',-1);
	return;
      }
    }

    if ($this->getConf('use_urlfile'))
    {
      if ( ! $this->_create_urlfile($ns,$fs))
      {
	return;
      }
    }

    return TRUE;
  }

  #----------------------------------------------------------------------------#

  /**
   *
   */
  private function _rename_fs($ns,$old_title)
  {
    $fs = $this->_fs($ns);
    if ( ! $fs)
    {
      return;
    }
    $fs_exist = @file_exists($fs);

    $fs_old = dirname($fs).'/'.$this->_clean_title($old_title);
    $fs_old_exist = @file_exists($fs_old);

    if ($fs_old_exist && ! $fs_exist)
    {
      # old folder exist, but new one is missing, rename old to new
      if ( ! @rename($fs_old,$fs))
      {
	msg('Der Filespace-Ordner "'.$fs_old.'" konnte nicht in "'.$fs.'" umbenannt werden: '.$php_errormsg,-1);
	return;
      }
    }

    elseif ($fs_old_exist && $fs_exist)
    {
      # old and new folder exist, try to remove old one
      $this->_remove_fs_bypath($fs_old);
      return;
    }

    elseif ( ! $fs_old_exist && ! $fs_exist)
    {
      # whether old, nor new one exist, create new one
      $this->_create_fs($ns);
    }

    else {
      # only current filespace exist, repair mode
      $this->_create_fs($ns);
    }

    return TRUE;
  }

  #----------------------------------------------------------------------------#

  /**
   *
   */
  private function _remove_fs($ns)
  {
    $fs = $this->_fs($ns);
    if ( ! $fs)
    {
      return;
    }
    return $this->_remove_fs_bypath($fs);
  }

  #----------------------------------------------------------------------------#

  /**
   *
   */
  private function _remove_fs_bypath($fs)
  {
    if ( ! $fs)
    {
      msg('Zum entfernen des Filespace wurde kein Pfad angegeben',-1);
      return;
    }

    if (@file_exists($fs))
    {
      $this->_remove_urlfile($fs);

      $items = $this->_fsitems($fs);
      if ($items)
      {
	msg('Im Filespace der Seite "'.$fs.'" befinden sich nocht Dateien, daher wird dieser nicht automatisch entfernt.',-1);
	return;
      }

      if (! @rmdir($fs))
      {
	msg('Der Filespace-Ordner "'.$fs.'" konnte nicht enfernt werden ('.$php_errormsg.')',-1);
	return;
      }
    }

    return TRUE;
  }

  #----------------------------------------------------------------------------#

  /**
   * Create URL link file for given namespace in it's corresponding filespace
   */
  private function _create_urlfile($ns,$fs)
  {
    global $conf;

    $url = "[InternetShortcut]\r\nURL=".wl($ns.':'.$conf['start'])."\r\n";

    $path = $fs.'/'.$this->getConf('urlfile');

    if ( ! @file_exists($path))
    {
      if (! @file_put_contents($path,$url))
      {
	msg('Die URL-Datei für diese Seite konnte in "'.$path.'" nicht erstellt werden ('.$php_errormsg.')',-1);
	return;
      }
    }

    return TRUE;
  }

  #----------------------------------------------------------------------------#

  /**
   *
   */
  private function _remove_urlfile($fs)
  {
    if ( ! $fs)
    {
      msg('Zum entfernen der URL-Datei wurde kein Pfad angegeben',-1);
      return;
    }

    $path = $fs.'/'.$this->getConf('urlfile');

    if (@file_exists($path))
    {
      if ( ! @unlink($path))
      {
	msg('Die URL-Datei für diese Wikiseite konnte nicht gelöscht werden: "'.$path.'" ('.$php_errormsg.')',-1);
	return;
      }
    }

    return TRUE;
  }

  #----------------------------------------------------------------------------#

  /**
   *
   */
  private function _title($ns)
  {
    global $conf;
    $title = trim(p_get_first_heading($ns.':'.$conf['start']));
    return $title;
  }

  #----------------------------------------------------------------------------#

  /**
   * Sanitize title to not contain filesystem vorbidden chars
   */
  private function _clean_title($title)
  {
    $bads = str_split($this->getConf('fs_badchars'));
    $title = str_replace($bads,'',$title);
    return $title;
  }

  #----------------------------------------------------------------------------#

  /**
   *
   */
  private function _fs($ns)
  {
    global $conf;

    // check cache first
    if (isset($this->_cache['ns'][$ns]))
    {
      return $this->_cache['ns'][$ns];
    }

    $fs = $this->getConf('fs_root');

    $ns_a = explode(':',$ns);
    $cur_ns = '';
    foreach($ns_a as $n)
    {
      $cur_ns .= ':'.$n;
      $title = $this->_title($cur_ns);
      if (! $title)
      {
        # component missing, cannot compute filespace path
	msg('Der Pfad zum Filespace-Ordner für den Namensraum "'.$ns.'" konnte nicht ermittelt werden. Für den Namensraum "'.$cur_ns.'" ist keine Startseite, bzw. kein Titel verfügbar.',-1);
        return;
      }
      $fs .= '/'.$this->_clean_title($title);
    }

    // store into cache
    $this->_cache['ns'][$ns] = $fs;

    return $fs;
  }

  #----------------------------------------------------------------------------#

  /**
   * Return foreign items in filespace directory (without URL file)
   */
  private function _fsitems($fs)
  {
    if ( ! $fs)
    {
      msg('Zum auslesen eines Filespace-Ordners wurde kein Pfad angegeben',-1);
      return;
    }

    if ( ! @file_exists($fs))
    {
      return;
    }

    $items = @scandir($fs);

    $items = array_diff($items,array('.','..',$this->getConf('urlfile')));

    return $items;
  }

  #----------------------------------------------------------------------------#

  /**
   * Return UNC path of filespace given as Unix path
   */
  function _fsunc($fs)
  {
    return $this->getConf('fs_root_unc').str_replace('/','\\',substr($fs,strlen($this->getConf('fs_root'))));
  }

  #----------------------------------------------------------------------------#

  /**
   *
   */
  private function _debug($msg,$lvl=0)
  {
    if ($this->getConf('debug'))
    {
      msg($msg,$lvl);
    }
  }

  #----------------------------------------------------------------------------#

}
?>
