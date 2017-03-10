<?php
/**
 * Options for the filespace plugin
 *
 * @author Oliver Geisen <oliver.geisen@kreisbote.de>
 */
# $meta[ <setting> ] = array( <setting class>, <param name> => <param value> );
$meta['debug'] = array('onoff');
$meta['fs_root'] = array('string');
$meta['fs_root_unc'] = array('string');
$meta['fs_sep'] = array('string','_max'=>1);
$meta['use_urlfile'] = array('onoff');
$meta['urlfile'] = array('string');
$meta['fs_badchars'] = array('string');

