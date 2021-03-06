<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Uploader extends CI_Controller {
	
	/* Constructor */
	
	public function __construct()
	{
		parent::__construct();
		$this->load->helper(array('jbimages','language'));
		
		// is_allowed is a helper function which is supposed to return False if upload operation is forbidden
		// [See jbimages/is_alllowed.php] 
		
		if (is_allowed() === FALSE)
		{
			exit;
		}
		
		// User configured settings
		$this->config->load('uploader_settings', TRUE);
	}
	
	/* Language set */
	
	private function _lang_set($lang)
	{
		// We accept any language set as lang_id in **_dlg.js
		// Therefore an error will occur if language file doesn't exist
		
		$this->config->set_item('language', $lang);
		$this->lang->load('jbstrings', $lang);
	}
	
	private function per_file_upload ($conf, $tmp_name='')
	{
		if ($tmp_name ? $this->upload->alt_do_upload($tmp_name) : $this->upload->do_upload()) // Success
		{
			// General result data
			$result = $this->upload->data();
			
			// Shall we resize an image?
			if ($conf['allow_resize'] and $conf['max_width'] > 0 and $conf['max_height'] > 0 and (($result['image_width'] > $conf['max_width']) or ($result['image_height'] > $conf['max_height'])))
			{				
				// Resizing parameters
				$resizeParams = array
				(
					'source_image'	=> $result['full_path'],
					'new_image'		=> $result['full_path'],
					'width'			=> $conf['max_width'],
					'height'		=> $conf['max_height']
				);
				
				// Load resize library
				$this->load->library('image_lib', $resizeParams);
				
				// Do resize
				$this->image_lib->resize();
			}

			// See if image should be constrained in the TinyMCE editor/viewer
			$result['viewer_width'] = $result['viewer_height'] = -1;
			if ($conf['max_viewer_width'] > 0 and ($result['image_width'] > $conf['max_viewer_width']))
			{
				$result['viewer_width'] = $conf['max_viewer_width'];
				$result['viewer_height'] = floor(($conf['max_viewer_width'] / $result['image_width']) * $result['image_height']);
			}
			if ($conf['max_viewer_height'] > 0 and ($result['viewer_height'] > $conf['max_viewer_height'] or $result['image_height'] > $conf['max_viewer_height']))
			{
				// this takes care of the case when both max_viewer_width and max_viewer_height are specified, and
				//   the height still exceeds the constraint after adjusting the width
				// as well as when only the max_viewer_height is specified
				$result['viewer_width'] = floor(($conf['max_viewer_height'] / $result['image_height']) * $result['image_width']);
				$result['viewer_height'] = $conf['max_viewer_height'];
			}
			
			// Add our stuff
			$result['result']		= "file_uploaded";
			$result['resultcode']	= 'ok';
			$result['file_name']	= $conf['img_path'] . '/' . $result['file_name'];
		}
		else // Failure
		{
			// Compile data for output
			$result['result']		= $this->upload->display_errors(' ', ' ');
			$result['resultcode']	= 'failed';
			$result['viewer_width'] = $result['viewer_height'] = -1;
		}
		return $result;
	}

	/* Default upload routine */
		
	public function upload ($lang='english')
	{
		// Set language
		$this->_lang_set($lang);
		
		// Get configuration data (we fill up 2 arrays - $config and $conf)
		
		$conf['img_path']			= $this->config->item('img_path',		'uploader_settings');
		$conf['allow_resize']		= $this->config->item('allow_resize',	'uploader_settings');
		
		$config['allowed_types']	= $this->config->item('allowed_types',	'uploader_settings');
		$config['max_size']			= $this->config->item('max_size',		'uploader_settings');
		$config['encrypt_name']		= $this->config->item('encrypt_name',	'uploader_settings');
		$config['overwrite']		= $this->config->item('overwrite',		'uploader_settings');
		$config['upload_path']		= $this->config->item('upload_path',	'uploader_settings');
		
		if (!$conf['allow_resize'])
		{
			$config['max_width']	= $this->config->item('max_width',		'uploader_settings');
			$config['max_height']	= $this->config->item('max_height',		'uploader_settings');
		}
		else
		{
			$conf['max_width']		= $this->config->item('max_width',		'uploader_settings');
			$conf['max_height']		= $this->config->item('max_height',		'uploader_settings');
			
			if ($conf['max_width'] == 0 and $conf['max_height'] == 0)
			{
				$conf['allow_resize'] = FALSE;
			}
		}
		$conf['max_viewer_width']		= $this->config->item('max_viewer_width',		'uploader_settings');
		$conf['max_viewer_height']		= $this->config->item('max_viewer_height',		'uploader_settings');
		
		// Load uploader
		$this->load->library('upload', $config);
		
		// Loop through multiple dragged'n'dropped FileReader files as needed
		$idx = 0;
		$results = array();
		while (true) {
			if (!isset($_POST['fileDragName'.$idx])) { break; }
			// process base64 data to temp file
			$imgData = str_replace(' ','+',$_POST['fileDragData'.$idx]);
			$imgData = substr($imgData,strpos($imgData,",")+1);
			$imgData = base64_decode($imgData);
			$tmpfname = tempnam(sys_get_temp_dir(), "B64");
			$handle = fopen($tmpfname, "w");
			fwrite($handle, $imgData);
			fclose($handle);
			// load up $_FILES['userfile'] for CI
			$_FILES['userfile'] = array();
			$_FILES['userfile']['name'] = $_POST['fileDragName'.$idx];
			$_FILES['userfile']['type'] = $_POST['fileDragType'.$idx];
			$_FILES['userfile']['tmp_name'] = $tmpfname;
			$_FILES['userfile']['error'] = 0; // no error
			$_FILES['userfile']['size'] = $_POST['fileDragSize'.$idx];
			array_push($results, $this->per_file_upload($conf, $tmpfname));
			unlink($tmpfname);
			$idx++;
		}

		// Loop through the standard $_FILES to upload files
		if (!empty($_FILES['multifile']) and count($_FILES['multifile']['name']) and $_FILES['multifile']['name'][0]) {
			for ($idx = 0; $idx < count($_FILES['multifile']['name']); $idx++) {
				$_FILES['userfile'] = array();
				$_FILES['userfile']['name'] = $_FILES['multifile']['name'][$idx];
				$_FILES['userfile']['type'] = $_FILES['multifile']['type'][$idx];
				$_FILES['userfile']['tmp_name'] = $_FILES['multifile']['tmp_name'][$idx];
				$_FILES['userfile']['error'] = $_FILES['multifile']['error'][$idx];
				$_FILES['userfile']['size'] = $_FILES['multifile']['size'][$idx];
				array_push($results, $this->per_file_upload($conf));
			}
		}

		$data['results'] = $results;
		$this->load->view('ajax_upload_result', $data);
	}
	
	/* Blank Page (default source for iframe) */
	
	public function blank($lang='english')
	{
		$this->_lang_set($lang);
		$this->load->view('blank');
	}
	
	public function index($lang='english')
	{
		$this->blank($lang);
	}
}

/* End of file uploader.php */
/* Location: ./application/controllers/uploader.php */
