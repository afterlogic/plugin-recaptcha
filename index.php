<?php

class_exists('CApi') or die();

class CRecaptchaPlugin extends AApiPlugin
{
	/**
	 * @param CApiPluginManager $oPluginManager
	 */
	public function __construct(CApiPluginManager $oPluginManager)
	{
		parent::__construct('1.0', $oPluginManager);

		$this->AddHook('api-app-data', 'PluginApiAppData');
		$this->AddHook('webmail-login-custom-data', 'PluginWebmailLoginCustomData');
		$this->AddHook('ajax.response-result', 'PluginAjaxResponseResult');
	}

	public function Init()
	{
		parent::Init();

		$this->AddJsFile('js/include.js');

		$this->IncludeTemplate('Login_LoginViewModel', 'Login-Before-Submit-Button', 'templates/recaptcha.html');
	}

	/**
	 * @param bool $bAddToLimit = false
	 * @param bool $bClear = false
	 * @return int
	 */
	private function captchaLocalLimit($bAddToLimit = false, $bClear = false)
	{
		$iResult = 0;
		$oApiIntegrator =\CApi::Manager('integrator');
		if ($oApiIntegrator)
		{
			$sKey = 'Login/Captcha/Limit/'.$oApiIntegrator->GetCsrfToken();
			$oCacher =\CApi::Cacher();
			if ($oCacher->IsInited())
			{
				if ($bClear)
				{
					$oCacher->Delete($sKey);
				}
				else
				{
					$sData = $oCacher->Get($sKey);
					if (0 < strlen($sData) && is_numeric($sData))
					{
						$iResult = (int) $sData;
					}

					if ($bAddToLimit)
					{
						$oCacher->Set($sKey, ++$iResult);
					}
				}
			}
		}

		return $iResult;
	}

	public function PluginAjaxResponseResult($sAction, &$aResponseItem)
	{
		if ('SystemLogin' === $sAction && is_array($aResponseItem) && isset($aResponseItem['Result']))
		{
			if (!$aResponseItem['Result'] && isset($GLOBALS['P7_RECAPTCHA_ATTRIBUTE_ON_ERROR']) && $GLOBALS['P7_RECAPTCHA_ATTRIBUTE_ON_ERROR'])
			{
				$aResponseItem['Captcha'] = true;
			}

			if (isset($GLOBALS['P7_RECAPTCHA_LIMIT_CHANGE']) && $GLOBALS['P7_RECAPTCHA_LIMIT_CHANGE'])
			{
				if ($aResponseItem['Result'])
				{
					$this->captchaLocalLimit(false, true);
				}
				else
				{
					$this->captchaLocalLimit(true);
				}
			}
		}
	}

	public function PluginWebmailLoginCustomData($mCustomData)
	{
		$sPublicKey =\CApi::GetConf('plugins.recaptcha.options.public-key', '');
		$sPrivateKey =\CApi::GetConf('plugins.recaptcha.options.private-key', '');

		if (!empty($sPublicKey) && !empty($sPrivateKey))
		{
			$iLimitCaptcha = (int)\CApi::GetConf('plugins.recaptcha.options.limit-count', 0);
			if (0 < $iLimitCaptcha)
			{
				$GLOBALS['P7_RECAPTCHA_LIMIT_CHANGE'] = true;
				$iLimitCaptcha -= $this->captchaLocalLimit();
			}

			if (1 === $iLimitCaptcha)
			{
				$GLOBALS['P7_RECAPTCHA_ATTRIBUTE_ON_ERROR'] = true;
			}
			else if (0 >= $iLimitCaptcha)
			{
				if (empty($mCustomData['RecaptchaResponseField']))
				{
					$GLOBALS['P7_RECAPTCHA_ATTRIBUTE_ON_ERROR'] = true;
					throw new \ProjectCore\Exceptions\ClientException(\ProjectCore\Notifications::CaptchaError);
				}

				include_once 'lib/recaptchalib.php';

				$oRecaptcha = new \ReCaptcha($sPrivateKey);
				$oResp = $oRecaptcha->verifyResponse($_SERVER['SERVER_ADDR'], (string) $mCustomData['RecaptchaResponseField']);

				if (!$oResp || !isset($oResp->success) || !$oResp->success)
				{
					$GLOBALS['P7_RECAPTCHA_ATTRIBUTE_ON_ERROR'] = true;
					throw new \ProjectCore\Exceptions\ClientException(\ProjectCore\Notifications::CaptchaError);
				}
			}
		}
	}

	public function PluginApiAppData($oDefaultAccount, &$aAppData)
	{
		if (isset($aAppData['Auth']) && !$aAppData['Auth'] &&
			isset($aAppData['Plugins']) && is_array($aAppData['Plugins']))
		{
			$sPublicKey =\CApi::GetConf('plugins.recaptcha.options.public-key', '');
			$sPrivateKey =\CApi::GetConf('plugins.recaptcha.options.private-key', '');

			if (!empty($sPublicKey) && !empty($sPrivateKey))
			{
				$aReCaptcha = array(
					'ShowOnStart' => false,
					'PublicKey' => $sPublicKey,
				);

				$iLimitCaptcha = (int)\CApi::GetConf('plugins.recaptcha.options.limit-count', 0);
				if (0 === $iLimitCaptcha)
				{
					$aReCaptcha['ShowOnStart'] = true;
				}
				else
				{
					$iLimitCaptcha -= $this->captchaLocalLimit();
					if (0 >= $iLimitCaptcha)
					{
						$aReCaptcha['ShowOnStart'] = true;
					}
				}

				$aAppData['Plugins']['ReCaptcha'] = $aReCaptcha;
			}
		}
	}
}

return new CRecaptchaPlugin($this);
