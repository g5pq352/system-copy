let acceptImg = "image/accept.png";
let deleteImg = "image/delete.png";

function activeData(rel, href, obj) {
	var src = '';
	$.ajax({
		type: "POST",
		url: "active_process.php",
		data: {
			d_id: rel,
			active: href
		},
		success: function(data) {
			obj.attr('href', data);
			if (data == 1) {
				src = acceptImg;
			} else {
				src = deleteImg;
			}
			obj.find('img').attr('src', src);
		}
	});
}

function activeDataU(rel, href, obj) {
	var src = '';
	$.ajax({
		type: "POST",
		url: "activeU_process.php",
		data: {
			d_id: rel,
			active: href
		},
		success: function(data) {
			obj.attr('href', data);
			if (data == 1) {
				src = acceptImg;
			} else {
				src = deleteImg;
			}
			obj.find('img').attr('src', src);
		}
	});
}

function activeMember(rel, href, obj) {
	var src = '';
	$.ajax({
		type: "POST",
		url: "activeM_process.php",
		data: {
			m_id: rel,
			active: href
		},
		success: function(data) {
			obj.attr('href', data);
			if (data == 1) {
				src = acceptImg;
			} else {
				src = deleteImg;
			}
			obj.find('img').attr('src', src);
		}
	});
}

function activeDataF(rel, href, obj) {
	var src = '';
	$.ajax({
		type: "POST",
		url: "activeF_process.php",
		data: {
			term_id: rel,
			active: href
		},
		success: function(data) {
			obj.attr('href', data);
			if (data == 1) {
				src = acceptImg;
			} else {
				src = deleteImg;
			}
			obj.find('img').attr('src', src);
		}
	});
}

function activeDataC(rel, href, obj) {
	var src = '';
	$.ajax({
		type: "POST",
		url: "activeC_process.php",
		data: {
			c_id: rel,
			active: href
		},
		success: function(data) {
			obj.attr('href', data);
			if (data == 1) {
				src = acceptImg;
			} else {
				src = deleteImg;
			}
			obj.find('img').attr('src', src);
		}
	});
}

function activeDataT(rel, href, obj) {
	var src = '';
	$.ajax({
		type: "POST",
		url: "activeT_process.php",
		data: {
			term_id: rel,
			active: href
		},
		success: function(data) {
			obj.attr('href', data);
			if (data == 1) {
				src = acceptImg;
			} else {
				src = deleteImg;
			}
			obj.find('img').attr('src', src);
		}
	});
}
function activeDataTT(rel, href, obj) {
	var src = '';
	$.ajax({
		type: "POST",
		url: "activeTT_process.php",
		data: {
			id: rel,
			active: href
		},
		success: function(data) {
			obj.attr('href', data);
			if (data == 1) {
				src = acceptImg;
			} else {
				src = deleteImg;
			}
			obj.find('img').attr('src', src);
		}
	});
}
function activeDataWL(rel, href, obj) {
	var src = '';
	$.ajax({
		type: "POST",
		url: "activeWL_process.php",
		data: {
			id: rel,
			active: href
		},
		success: function(data) {
			obj.attr('href', data);
			if (data == 1) {
				src = acceptImg;
			} else {
				src = deleteImg;
			}
			obj.find('img').attr('src', src);
		}
	});
}
function activeDataTX(rel, href, obj) {
	var src = '';
	$.ajax({
		type: "POST",
		url: "activeTX_process.php",
		data: {
			id: rel,
			active: href
		},
		success: function(data) {
			obj.attr('href', data);
			if (data == 1) {
				src = acceptImg;
			} else {
				src = deleteImg;
			}
			obj.find('img').attr('src', src);
		}
	});
}
function levelData(rel, href, obj) {
	var src = '';
	$.ajax({
		type: "POST",
		url: "activeL_process.php",
		data: {
			d_id: rel,
			level: href
		},
		success: function(data) {
			obj.attr('href', data);
			if (data == 1) {
				src = acceptImg;
			} else {
				src = deleteImg;
			}		
			obj.find('img').attr('src', src);
		}
	});
}
function activeM(rel, href, obj) {
	var src = '';
	$.ajax({
		type: "POST",
		url: "activeM_process.php",
		data: {
			user_id: rel,
			active: href
		},
		success: function(data) {
			obj.attr('href', data);
			if (data == 1) {
				src = acceptImg;
			} else {
				src = deleteImg;
			}
			obj.find('img').attr('src', src);
		}
	});
}
function activeTM(rel, href, obj) {
	var src = '';
	$.ajax({
		type: "POST",
		url: "activeTM_process.php",
		data: {
			m_id: rel,
			active: href
		},
		success: function(data) {
			obj.attr('href', data);
			if (data == 1) {
				src = acceptImg;
			} else {
				src = deleteImg;
			}
			obj.find('img').attr('src', src);
		}
	});
}
$(document).ready(function() {

	$('.activeCh').on( "click", function() {
		activeData($(this).attr('rel'), $(this).attr('href'), $(this));
		return false;
	});

	$('.activeChU').on( "click", function() {
		activeDataU($(this).attr('rel'), $(this).attr('href'), $(this));
		return false;
	})

	$('.activeChM').on( "click", function() {
		activeMember($(this).attr('rel'), $(this).attr('href'), $(this));
		return false;
	})

	$('.activeChMember').on( "click", function() {
		activeM($(this).attr('rel'), $(this).attr('href'), $(this));
		return false;
	})

	//productsF_list.php
	$('.activeCF').on( "click", function() {
		activeDataF($(this).attr('rel'), $(this).attr('href'), $(this));
		return false;
	});

	//class
	$('.activeChC').on( "click", function() {
		activeDataC($(this).attr('rel'), $(this).attr('href'), $(this));
		return false;
	});

	//services class
	$('.activeChT').on( "click", function() {
		activeDataT($(this).attr('rel'), $(this).attr('href'), $(this));
		return false;
	});

	//services class
	$('.activeChL').on( "click", function() {
		levelData($(this).attr('rel'), $(this).attr('href'), $(this));
		return false;
	});
	// taxonomy_types
	$('.activeChTT').on( "click", function() {
		activeDataTT($(this).attr('rel'), $(this).attr('href'), $(this));
		return false;
	});
	// languages
	$('.activeChWL').on( "click", function() {
		activeDataWL($(this).attr('rel'), $(this).attr('href'), $(this));
		return false;
	});
	//taxonomies
	$('.activeChTX').on( "click", function() {
		activeDataTX($(this).attr('rel'), $(this).attr('href'), $(this));
		return false;
	});
	//taxonomies
	$('.activeChTM').on( "click", function() {
		activeTM($(this).attr('rel'), $(this).attr('href'), $(this));
		return false;
	});
});