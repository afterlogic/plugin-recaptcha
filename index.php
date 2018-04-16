<?php
/*
The MIT License (MIT)
Copyright (c) 2016, Afterlogic Corp.

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

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

				include_once 'lib/autoload.php';

				$oRecaptcha = new \ReCaptcha\ReCaptcha($sPrivateKey, new \ReCaptcha\RequestMethod\CurlPost());
				$oResp = $oRecaptcha->verify((string) $mCustomData['RecaptchaResponseField']);

				if (!$oResp || !$oResp->isSuccess())
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
