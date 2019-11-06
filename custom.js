(function ($) {
	window.ICDEVOPS_Sislrc = {
		config: {},
		init: function () {
			ICDEVOPS_Sislrc.sectionsSetup();
		},
		sectionsSetup: function () {
			var $parent = $('.ic-devops-sislrc-tabs-wrapper');
			var $menu = $parent.find('.ic-menu');
			var $tabs = $menu.find('li');
			var $content = $parent.find('.ic-menu-items');
			var $contentItems = $content.find('li');
			$tabs.unbind('click');
			$tabs.each(function () {
				$(this).bind('click', function (e) {
					ICDEVOPS_Sislrc.activate($tabs, $contentItems, $(this));
				});
			});
			var active = $menu.data('active');
			ICDEVOPS_Sislrc.activate($tabs, $contentItems, $('#'+active));
		},
		activate: function($tabs, $contentItems, $el) {
			$tabs.removeClass('on');
			$contentItems.removeClass('on');
			$el.addClass('on');
			$('#' + $el.attr('id') + '-content').addClass('on');
			$tabs.data('active', $el.attr('id'));
		},
	};

	$(document).ready(function () {
		ICDEVOPS_Sislrc.init();
	});

})(jQuery);
