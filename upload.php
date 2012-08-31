<?php

	if (!isset($_FILES['photo']) && !isset($_POST['photo'])) {
		exit('请求错误');
	}

	/**
	 * 给文件名添加 @2x
	 * param $name 文件名
	 * return string
	 */
	function add_2x($name) {
		$struct = explode('.', $name);

		if (substr($struct[0], -3) !== '@2x') {
			// 如果不存在 @2x 字样则添加
			$struct[0] .= '@2x';
			return implode('.', $struct);
		}

		return $name;
	}

	define('BASE_DIR', dirname($_SERVER['SCRIPT_FILENAME']));
	define('UPLOAD_DIR', BASE_DIR . '/upload/' . time() . '-' . rand(0, 9) . '/');

	$files = array(
		'1x' => array(),
		'2x' => array(),
	);
	// 保存文件列表的数组，以备后用

	if (isset($_POST['photo'])) {

		$files['1x'] = array_keys($_POST['photo']);

		foreach ($_POST['photo'] as $name => $file) {
			// 判断每一个文件是否正确后，保存至特定目录

			$content = base64_decode(str_replace('data:image/png;base64,', '', $file));

			if (!is_array(getimagesizefromstring($content))) {
				exit('文件仅限图片');
			}

			$upload_filename = add_2x($name);
			// 文件名增加 @2x 字样

			@mkdir(UPLOAD_DIR);
			// 新建特定目录

			if (file_put_contents(UPLOAD_DIR . $upload_filename, $content) === FALSE) {
				exit('保存失败');
			}

			$files['2x'][] = $upload_filename;
			// 保存到文件列表数组

		}

	} else {

		$files['1x'] = $_FILES['photo']['name'];

		foreach ($_FILES['photo']['error'] as $key => $error) {
			// 判断每一个文件是否正确后，保存至特定目录

			if ($error !== UPLOAD_ERR_OK) {
				exit('上传失败');
			} elseif (is_uploaded_file($_FILES['photo']['tmp_name'][$key]) === FALSE) {
				exit('文件来源非法');
			} elseif (!is_array(getimagesize($_FILES['photo']['tmp_name'][$key]))) {
				exit('文件仅限图片');
			}

			$upload_filename = add_2x(basename($_FILES['photo']['name'][$key]));
			// 文件名增加 @2x 字样

			@mkdir(UPLOAD_DIR);
			// 新建特定目录

			if (move_uploaded_file($_FILES['photo']['tmp_name'][$key], UPLOAD_DIR . $upload_filename) === FALSE) {
				exit('保存失败');
			}

			$files['2x'][] = $upload_filename;
			// 保存到文件列表数组

		} // 所有文件上传成功，开始进行缩放处理

	}

	foreach($files['2x'] as $key => $name) {

		$photo_meta = getimagesize(UPLOAD_DIR . $name);
		// 获得 2x 图的尺寸

		$thumb_size = array(
			'width' => intval(ceil($photo_meta[0] / 2)),
			'height' => intval(ceil($photo_meta[1] / 2)),
		);
		// 计算 1x 图的尺寸

		$photo_meta = getimagesize(UPLOAD_DIR . $name);
		// 获取原图的信息

		$photo = imagecreatefrompng(UPLOAD_DIR . $name);
		// 读取原图

		$thumb = imagecreatetruecolor($thumb_size['width'], $thumb_size['height']);
		// 创建缩略图的画布

		imagealphablending($thumb, false);
		// 关闭混色模式

		$color = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
		imagefill($thumb, 0, 0, $color);
		// 获取透明色并填充

		imagesavealpha($thumb, true);
		// 保存缩略图时保留完整的 alpha 通道信息

		imagecopyresampled($thumb, $photo, 0, 0, 0, 0, $thumb_size['width'], $thumb_size['height'], $photo_meta[0], $photo_meta[1]);
		// 进行缩放

		imagepng($thumb, UPLOAD_DIR . $files['1x'][$key]);
		// 输出缩略图

		imagedestroy($thumb);
		imagedestroy($photo);

	} // 所有文件缩放完毕，开始进行打包
	
	require_once('zip.lib.php');
	
	$archive = new zipfile();
	
	foreach ($files['1x'] as $name) {
		$archive->addFile(file_get_contents(UPLOAD_DIR . $name), $name);
		@unlink(UPLOAD_DIR . $name);
	}

	foreach ($files['2x'] as $name) {
		$archive->addFile(file_get_contents(UPLOAD_DIR . $name), $name);
		@unlink(UPLOAD_DIR . $name);
	}
	
	$archive->output(UPLOAD_DIR . 'archive.zip');

	header('Content-Type: application/zip');
	header('Content-disposition: attachment; filename="archive.zip"');
	@readfile(UPLOAD_DIR . 'archive.zip');
	// 产生相应 Header 并读取文件内容

	foreach ($files['1x'] as $name) {
		@unlink(UPLOAD_DIR . $name);
	}

	foreach ($files['2x'] as $name) {
		@unlink(UPLOAD_DIR . $name);
	}

	@unlink(UPLOAD_DIR . 'archive.zip');
	@rmdir(UPLOAD_DIR);
	// 删除对应文件