(function () {
	
	var 
		bShown = false
	;
	
	function ShowRecaptcha()
	{
		if (window.grecaptcha)
		{
			if (!bShown)
			{
				var
					oSettings = AfterLogicApi.getPluginSettings('ReCaptcha'),
					sKey = oSettings ? oSettings.PublicKey : ''
				;
				
				window.grecaptcha.render('recaptcha-place', {
					'sitekey': sKey,
					'theme': 'light',
					'lang': AfterLogicApi.getSetting('DefaultLanguageShort')
				});
			}
			else
			{
				window.grecaptcha.reset();
			}

			bShown = true;
		}
	}
	
	function StartRecaptcha()
	{
		if (!window.grecaptcha)
		{
			window.ShowRecaptcha = ShowRecaptcha;
			$.getScript('https://www.google.com/recaptcha/api.js?onload=ShowRecaptcha&render=explicit');
		}
		else
		{
			ShowRecaptcha();
		}
	}
	
	AfterLogicApi.addPluginHook('view-model-on-show', function (sViewModelName, oViewModel) {
		var
			oSettings = AfterLogicApi.getPluginSettings('ReCaptcha'),
			bShowOnStart = oSettings ? oSettings.ShowOnStart : false
		;
		
		if ('CWrapLoginViewModel' === sViewModelName && oViewModel && bShowOnStart)
		{
			StartRecaptcha();
		}
	});
	
	AfterLogicApi.addPluginHook('ajax-default-request', function (sAction, oParameters) {
		if ('SystemLogin' === sAction && oParameters && bShown && window.grecaptcha)
		{
			oParameters['CustomRequestData'] = oParameters['CustomRequestData'] || {};
			oParameters['CustomRequestData'] = {
				'RecaptchaResponseField': window.grecaptcha.getResponse()
			};
		}
	});

	AfterLogicApi.addPluginHook('ajax-default-response', function (sAction, oData) {
		if ('SystemLogin' === sAction)
		{
			if (!oData || !oData['Result'])
			{
				if(oData && oData['Captcha'])
				{
					StartRecaptcha();
				}
			}
		}
	});
})();