
$(document).ready(function() {
	changeSectionHeight();

	if ( $(".edit-bar").length ) {
		// zobrazime jinak skryte ikonky pro pridavani potomku (novych listu XML)
		$(".type-list .edit-bar .sibling, .type-list .edit-bar .remove-child, .type-list .edit-bar .child").show();

		$('.type-list .edit-bar .sibling').click(function() {
			duplicateNode($(this));
		});

		$(".edit-bar .remove-child").click(function() {
			removeNode($(this));
		});

		$(".edit-bar .create-child").click(function() {
			generateNode($(this));
		});

		// $('.edit-bar .child').click(function() {
		//	createNode($(this));
		// });
	}

	// tooltip
	$('.tooltip .icon-help').each(function() {
		initDefaultTooltip($(this));
	});

	/* hide alerts after some time - only successfull */
	setTimeout(function() {
		$('.alert.success').fadeOut();
	}, 5000); /* 5s */

	/* when range input type, add number of current value before input */
	$("input[type='range']").each(function(i, e) {
		tmp = $("<input>").attr({
			'class': 'range-cover-number',
			type: 'number',
			disabled: 'disabled',
			value: e.value
		}).text(e.value);
		$(e).after(tmp);
		$(e).bind('change', function() {
			$(e).next('.range-cover-number').val(e.value);
		});
	});

	showIconsOnLeafLine();
});

$(window).resize(function() {
	changeSectionHeight();
	showIconsOnLeafLine();
});

function changeSectionHeight() {
	$("body > section, body > section#content").css('min-height', '0%').height($(window).height());
}

function showIconsOnLeafLine() {
	/*
	if ($(window).width() < 1550) {
		$('.root').delegate(".leaf-line", 'hover', function() {
			$(this).find('.icon-bar').fadeToggle();
		});
	} else {
		$('.root').undelegate('.leaf-line', hover);
	}
	*/
}

function initDefaultTooltip($el) {
	$el.gips({ 'theme': 'blue', placement: 'top', animationSpeed: 100, bottom: $el.parent().parent().parent().outerHeight(), text: $el.siblings('.tooltip-description').text() });
}

function duplicateNode($elem) {
	$cover = createFormUnderlay($elem);

	var xPath = $elem.attr('rel'),	// parent xPath - in anchor attribute rel
	level = findLevelValue($elem);

	$currentParent = $elem.parent().parent();
	$currentParentLevel = $elem.parents('.level-' + level);

	// generate new form
	$form = generateFormObject('duplicatedNodeForm');

	// current element clone - with all children
	$newClone = $elem.parent().parent().clone();
	$form.html($newClone);
	if ($elem.parent().parent().is(':first-child')) {
		$elem.parent().parent().nextAll("*").each(function(i, el) {
			$form.append($(el).clone());
		});
	}

	// remove all state nodes (this won't be duplicated)
	$form.find('.state').remove();

	// create hidden input with path to the duplicated node
	$elementWithParentXpath = $("<input>")
		.attr({
			type: 'hidden',
			name: "duplicatedNodeForm[parent]",
			value: xPath
		});
	$form.prepend($elementWithParentXpath);

	// we have to modify inputs for all children
	$form.children().each(function(i, el) {
		modifyInputAttributes(el, i, 'duplicatedNodeForm');
	});

	// create submit and close button
	createSubmitButton($form, "Save changes");
	createCloseButton($cover, $form);

	// append created form into the parent
	$currentParentLevel.append($form);

	unwrapCoverForm($currentParentLevel, $cover);

	// finally, initialization of Tooltip on cloned elements
	// must be after showing form
	$form.find('.tooltip .icon-help').each(function() {
		initDefaultTooltip($(this));
	});
}

function removeNode($elem) {
	createFormUnderlay($elem);

	var xPath = $elem.attr('rel');	// parent XPath - from attribute rel
	level = findLevelValue($elem);

	$currentParent = $elem.parent().parent();
	$currentParentLevel = $elem.parents('.level-' + level);

	// generate new form
	$form = generateFormObject('removeNodeForm');

	// create hidden input with path to the duplicated node
	$elementWithParentXpath = $("<input>")
		.attr({
			type: 'hidden',
			name: "removeNodeForm[parent]",
			value: xPath
		});
		$form.prepend($elementWithParentXpath);

	// create submit and close button
	createSubmitButton($form, "Delete record");
	createCloseButton($cover, $form);

	// append created form into the parent
	$currentParentLevel.append($form);

	unwrapCoverForm($currentParentLevel, $cover);
}

function generateNode($elem) {
	createFormUnderlay($elem);

	var rel = $elem.attr('rel').split('_');	// rel[0] - xPath, rel[1] - serialized route params
	level = findLevelValue($elem);

	$currentParent = $elem.parent().parent();
	$currentParentLevel = $elem.parents('.level-' + level);

	xPath = rel[0];
	loadUrl = rel[1];

	// generate new form
	$form = generateFormObject('generateNodeForm');

	// create hidden input with path to the duplicated node
	$elementWithParentXpath = $("<input>")
		.attr({
			type: 'hidden',
			name: "generateNodeForm[parent]",
			value: xPath
		});
		$form.prepend($elementWithParentXpath);

	// load URL with HTML form
	$tmpDiv = $("<div>").addClass('root');
	$tmpDiv.load(document.location.protocol + "//" + document.location.host + loadUrl, function() {
		// we have to modify inputs for all children
		$tmpDiv.children().each(function(i, el) {
			modifyInputAttributes(el, i, 'generatedNodeForm');
		});
	});
	$form.append($tmpDiv);

	// create submit and close button
	createSubmitButton($form, "Add new list");
	createCloseButton($cover, $form);

	// append created form into the parent
	$currentParentLevel.append($form);
	unwrapCoverForm($currentParentLevel, $cover);
}

function createFormUnderlay($elem) {
	// find cover - if we are on state, it would be state column
	if ($elem.parents('#state').length) {
		$cover = $("#state");

	// or we are on config
	} else if ($elem.parents('#config').length) {
		$cover = $("#config");

	// or we have single column layout
	} else {
		$cover = $("#content");
	}

	// if form-underlay already exists, will be removed
	if ( $cover.find(".form-underlay").length === 0 ) {
		$cover.find('.form-underlay').remove();
	}

	// append form-underlay to cover
	$cover.append($("<div>").addClass('form-underlay'));
	$cover.append($("<div>").addClass('form-cover'));

	// we have to count new dimensions for new form-underlay
	// and fill it over whole cover part
	var nWidth = $cover.outerWidth(),
		nHeight = $cover[0].scrollHeight + $elem.parent().parent().parent().outerHeight() + 150; // 150 px for buttons

	// we have to set form to fill cover (from top)
	$cover.find(".form-underlay").width(nWidth).height(nHeight).css({
		'margin-top': 0,
		'margin-left': 0 - parseInt($cover.css('padding-left'), 10)
	});

	return $cover;
}

function findLevelValue($elem) {
	var levelRegex = /level-(\d+)/,	// regex for level value
		level = $elem.parents('.leaf-line').attr('class');	// parent class for level

	if ( level.match(levelRegex) === null || ( level.match(levelRegex) !== null && isNaN(level.match(levelRegex)[1]) ) ) {

		// level does not have to be by first parent, could be on previous level too
		if ( $elem.parents('.leaf-line').parent().length ) {
			level = $elem.parents('.leaf-line').parent().attr('class');
			if ( level.match(levelRegex) === null || ( level.match(levelRegex) !== null && isNaN(level.match(levelRegex)[1]) ) ) {
				level = 0;
			} else {
				level = level.match(levelRegex)[1];
			}
		} else {
			level = 0;
		}
		
	} else {
		level = level.match(levelRegex)[1];
	}

	return level;
}

function generateFormObject(formName) {
	// new form object - if is not created, we will create new one
	if ( $(".generatedForm").length ) {
		$form = $('.generatedForm');
	} else {
		// vytvorime formular
		$form = $("<form>")
			.attr({
				action: "#",
				method: "POST",
				name: formName,
				'class': 'generatedForm'
			});
	}

	return $form;
}

function modifyInputAttributes(el, newIndex, newInputName) {
	uniqueId = String(getUniqueId());

	// clean edit-bar html
	$(el).find('.edit-bar').html('');

	// find all input in this level
	inputArr = $.merge( $(el).children('input'), $(el).children('.config-value-cover').find('input') );

	// modify every input
	inputArr.each(function(i, e) {
		// rewrite name to duplicatedNodeForm
		if ( $(e).attr('name') ) {
			elName = $(e).attr('name').replace('configDataForm', newInputName);
			$(e).attr('name', elName);

			if ( $(e).attr('type') == 'range' ) {
				$(e).bind('change', function() {
					$(e).next('.range-cover-number').val(e.value);
				});
			}

			// check, if default attribute is defined
			// if yes, default value will be used instead of current value
			if ( $(e).attr('default') !== "" ) {
				if ( $(e).attr('type') == 'radio' ) {
					if ( $(e).attr('value') == $(e).attr('default') ) {
						$(e).parent().parent().find('input[checked=checked]').removeAttr('checked');
						$(e).attr('checked', 'checked');
					}
				} else {
					if ( $(e).attr('value') != $(e).attr('default') ) {
						$(e).attr('value', $(e).attr('default'));
					}
				}
			}
			// we have to remove disabled attribute on input (user should be able to edit this value)
			$(e).removeAttr('disabled');
		}
	});

	// recursively find next level of input
	if ( $(el).children('.leaf-line, div[class*=level]').length ) {
		$(el).children('.leaf-line, div[class*=level]').each(function(j, elem) {
			modifyInputAttributes(elem, j, newInputName);
		});
	}
}

function createSubmitButton($form, inputValue) {
	// create form submit - if already exists, we will delete
	// it and append to the end
	if ( $form.children("input[type=submit]").length ) {
		$form.children("input[type=submit]").remove();
	}
	$elementSubmit = $("<input>")
		.attr({
			type: 'submit',
			value: inputValue
		});
	$form.append($elementSubmit);

	// bind click function to send form
	$elementSubmit.bind('click', function() {
		$form.submit();
	});
}

function createCloseButton($cover, $form) {
	// create close button and append at the end of form
	$closeButton = $("<a href='#' title='Close' class='close red button'>Close</a>");
	$form.append($closeButton);

	// bind click and keydown event
	$closeButton.bind('click', function() {
		wrapCoverForm($cover, $form);
	});
	$(document).bind('keydown', function(event) {
		if ( event.which == 27 ) {
			//event.preventDefault();
			wrapCoverForm($cover, $form);
		}
	});
}

// wrap unwrapped form back to cover whole tree form
function wrapCoverForm($cover, $form) {
	$originalForm = $cover.children('form');
	$cover.find('.root').wrap($originalForm);
	$form.remove();
	$('.form-underlay').remove();
	$('.form-cover').remove();
}

// unwrap old form (we can't have two forms inside in HTML
// if we want to work properly), so old form will stay
// alone prepending cover - so we can wrap it always back,
// for example while close button is clicked
function unwrapCoverForm($currentParentLevel, $cover) {
	$oldForm = $currentParentLevel.parents('form').clone();
	$oldForm.html('');
	$cover.prepend($oldForm);
	$currentParentLevel.parents('form').children('.root').unwrap();
}

function getUniqueId() {
	return Math.floor( Math.random()*99999 );
}

function l (str) {
	if (console.log) console.log(str);
}




/*
function createNode($elem) {
	$cover = createFormUnderlay($elem);

	var xPath = $elem.attr('rel'),	// parent xPath - in anchor attribute rel
		$editBar = $elem.parent().clone();	// editBar clone - we will modify it below

	level = findLevelValue($elem);

	// vytvorime div obalujici inputy
	level = parseInt(level, 10) + 1;
	$cover = $("<div>").addClass('leaf-line').addClass('level-' + String(level)).addClass('generated');

	$form = generateFormObject('newNodeForm');

	uniqueId = String(getUniqueId());
	// input pro nazev elementu
	$elementName = $("<input>")
		.attr({
			name: 'newNodeForm[label_' + uniqueId + '_' + xPath + ']',
			type: 'text',
			'class': 'label'
		});
	$cover.append($elementName);
	$elementName.before($("<span>").addClass('dots'));

	// input pro hodnotu elementu
	$elementValue = $("<input>")
		.attr({
			name: 'newNodeForm[value_' + uniqueId + '_' + xPath + ']',
			type: 'text',
			'class': 'value'
		});
	$cover.append($elementValue);

	// upravime si naklonovany editBar - pridame tridu pro odliseni vygenerovaneho baru
	$editBar.children("img").addClass('generated');
	// delegujeme click akci na nove vytvoreny element editBar
	$editBar.children("img.sibling").on('click', function() {
		createNode($(this));
	});

	// ke coveru pripojime editBar
	$cover.prepend($editBar);

	// pokud se jedna o vygenerovanou cast, pridame potomka k rodici (obalujici div)
	level = level - 1;
	$currentParent = $elem.parent().parent();
	$currentParentLevel = $elem.parents('.level-' + level);

	if ( $currentParentLevel.length && $currentParentLevel.hasClass('leaf-line') ) {
		l ( "ano");
		// jelikoz pridavame dalsi potomky, musime vlozit aktualni inputy rodice take do coveru leaf-line
		$leaf = $("<div>").addClass('leaf-line').html($currentParent.html());

		// formular jiz mame vytvoreny, pouze tedy pridame
		if ( $('.generatedForm').length ) {
			$currentParent.removeClass('leaf-line').html('').prepend($leaf).append($cover);
		// jinak se jedna o prvni node, vytvorime tedy formular
		} else {
			$currentParent.removeClass('leaf-line').html('').prepend($leaf).append($form);
			$form.append($cover);
			$leaf.addClass('active');
		}

		// jelikoz jsme premistili ikonky do coveru ($leaf), musime jim znova pridat akci click
		$currentParent.children("form .leaf-line:first-child").children('.edit-bar').children("img").on('click', function() {
			createNode($(this));
		});

		l($cover);

	} else {
		l ( "ne");
		// formular jiz mame vytvoreny, pouze tedy pridame
		if ( $('.generatedForm').length ) {
			if ( $currentParent.parents('.generatedForm').length ) {
				$currentParent.nextAll(":last").after($cover);
			} else {
				$(".generatedForm").append($cover);
			}
		// jinak se jedna o prvni node, vytvorime tedy formular
		} else {
			$currentParentLevel.append($form);
			$form.append($cover);
			$elem.parents('.leaf-line').addClass('active');
		}
	}

	// nyni je nutne upravit xPath vygenerovanych inputu a ikonek
	$originalInput = $cover.children('input.value, input.label');
	newIndex = $cover.index();
	if (newIndex < 1) newIndex = 1;

	$originalInput.each(function(i,e) {
		newXpath = $(e).attr('name') + '[' + newIndex + ']';
		$(e).attr('name', newXpath);
	});

	// nesmime zapomenout pridat pozmeneny xPath take k ikonkam pro pridani dalsi node
	$newRel = $cover.children('.edit-bar').children('img');
	$newRel.attr('rel', $newRel.attr('rel') + '][' + newIndex);

	// nakonec vytvorime submit - pokud existuje, smazeme jej
	if ( $form.children("input[type=submit]").length ) {
		$form.children("input[type=submit]").remove();
	}
	$elementSubmit = $("<input>")
		.attr({
			type: 'submit',
			value: 'Save changes'
		});
	$form.append($elementSubmit);
}
*/
