(function () {
	
	var 
		bShown = false
	;
	
	function ShowRecaptcha()
	{
		if (window.Recaptcha)
		{
			if (bShown)
			{
				window.Recaptcha.reload();
			}
			else
			{
				var
					oSettings = AfterLogicApi.getPluginSettings('ReCaptcha'),
					sKey = oSettings ? oSettings.PublicKey : ''
				;
				
				window.Recaptcha.create(sKey, 'recaptcha-place', {
					'theme': 'white',
					'lang': AfterLogicApi.getSetting('DefaultLanguageShort')
				});
			}

			bShown = true;
		}
	}
	
	function StartRecaptcha()
	{
		if (!window.Recaptcha)
		{
			$.getScript('//www.google.com/recaptcha/api/js/recaptcha_ajax.js', ShowRecaptcha);
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
		if ('SystemLogin' === sAction && oParameters && bShown && window.Recaptcha)
		{
			oParameters['CustomRequestData'] = oParameters['CustomRequestData'] || {};
			oParameters['CustomRequestData'] = {
				'RecaptchaChallengeField': window.Recaptcha.get_challenge(),
				'RecaptchaResponseField': window.Recaptcha.get_response()
			};
		}
	});

	AfterLogicApi.addPluginHook('ajax-default-response', function (sAction, oData) {
		if ('SystemLogin' === sAction)
		{
			if (!oData || !oData['Result'])
			{
				if (bShown && window.Recaptcha)
				{
					window.Recaptcha.reload();
				}
				else if (oData && oData['Captcha'])
				{
					StartRecaptcha();
				}
			}
		}
	});
})();