<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\controllers;

use Craft;
use craft\app\base\Volume;
use craft\app\elements\Asset;
use craft\app\errors\ModelException;
use craft\app\errors\HttpException;
use craft\app\helpers\JsonHelper;
use craft\app\helpers\UrlHelper;
use craft\app\web\Controller;

/**
 * The VolumeController class is a controller that handles various actions related to asset volumes, such as
 * creating, editing, renaming and reordering them.
 *
 * Note that all actions in the controller require an authenticated Craft session via [[Controller::allowAnonymous]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class VolumesController extends Controller
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc Controller::init()
	 *
	 * @throws HttpException
	 * @return null
	 */
	public function init()
	{
		// All asset volume actions require an admin
		$this->requireAdmin();
	}

	/**
	 * Shows the asset volume list.
	 *
	 * @return null
	 */
	public function actionVolumeIndex()
	{
		$variables['volumes'] = Craft::$app->volumes->getAllVolumes();
		$this->renderTemplate('settings/assets/volumes/_index', $variables);
	}

	/**
	 * Edit an asset volume.
	 *
	 * @param int         $volumeId       The volume’s ID, if editing an existing volume.
	 * @param Volume $volume         The volume being edited, if there were any validation errors.
	 *
	 * @throws HttpException
	 * @return null
	 */
	public function actionEditVolume($volumeId = null, Volume $volume = null)
	{
		$this->requireAdmin();

		if ($volume === null)
		{
			if ($volumeId !== null)
			{
				$volume = Craft::$app->volumes->getVolumeById($volumeId);

				if (!$volume)
				{
					throw new HttpException(404, "No volume exists with the ID '$volumeId'.");
				}
			}
			else
			{
				$volume = Craft::$app->volumes->createVolume('craft\app\volumes\Local');
			}
		}

		if (Craft::$app->getEdition() == Craft::Pro)
		{
			$allVolumeTypes = Craft::$app->volumes->getAllVolumeTypes();
			$volumeInstances = [];
			$volumeTypeOptions = [];

			foreach ($allVolumeTypes as $class)
			{
				if ($class === $volume->getType() || $class::isSelectable())
				{
					$volumeInstances[$class] = Craft::$app->volumes->createVolume($class);

					$volumeTypeOptions[] = [
						'value' => $class,
						'label' => $class::displayName()
					];
				}
			}
		}
		else
		{
			$volumeTypeOptions = [];
			$volumeInstances = [];
			$allVolumeTypes = null;
		}

		$isNewVolume = !$volume->id;

		if ($isNewVolume)
		{
			$title = Craft::t('app', 'Create a new asset volume');
		}
		else
		{
			$title = $volume->name;
		}

		$crumbs = [
			['label' => Craft::t('app', 'Settings'), 'url' => UrlHelper::getUrl('settings')],
			['label' => Craft::t('app', 'Assets'),   'url' => UrlHelper::getUrl('settings/assets')],
			['label' => Craft::t('app', 'Volumes'),  'url' => UrlHelper::getUrl('settings/assets')],
		];

		$tabs = [
			'settings'    => ['label' => Craft::t('app', 'Settings'),     'url' => '#assetvolume-settings'],
			'fieldlayout' => ['label' => Craft::t('app', 'Field Layout'), 'url' => '#assetvolume-fieldlayout'],
		];

		$this->renderTemplate('settings/assets/volumes/_edit', [
			'volumeId' => $volumeId,
			'volume' => $volume,
			'isNewVolume' => $isNewVolume,
			'volumeTypes' => $allVolumeTypes,
			'volumeTypeOptions' => $volumeTypeOptions,
			'volumeInstances' => $volumeInstances,
			'title' => $title,
			'crumbs' => $crumbs,
			'tabs' => $tabs
		]);
	}

	/**
	 * Saves an asset volume.
	 *
	 * @return null
	 */
	public function actionSaveVolume()
	{
		$this->requirePostRequest();

		$existingVolumeId = Craft::$app->getRequest()->getBodyParam('volumeId');

		if ($existingVolumeId)
		{
			$volume = Craft::$app->volumes->getVolumeById($existingVolumeId);
		}
		else
		{
			if (Craft::$app->getEdition() == Craft::Pro)
			{
				$volumeType = Craft::$app->getRequest()->getBodyParam('type');
			}
			else
			{
				$volumeType = 'craft\app\volumes\Local';
			}

			$volume = Craft::$app->volumes->createVolume($volumeType);
		}

		$volume->name   = Craft::$app->getRequest()->getBodyParam('name');
		$volume->handle = Craft::$app->getRequest()->getBodyParam('handle');
		$volume->url    = Craft::$app->getRequest()->getBodyParam('url');

		$typeSettings = Craft::$app->getRequest()->getBodyParam('types');

		if (isset($typeSettings[$volume->type]))
		{
			if (!$volume->settings)
			{
				$volume->settings = [];
			}

			$volume->settings = array_merge($volume->settings, $typeSettings[$volume->type]);
		}

		// Set the field layout
		$fieldLayout = Craft::$app->fields->assembleLayoutFromPost();
		$fieldLayout->type = Asset::className();
		$volume->setFieldLayout($fieldLayout);

		if (Craft::$app->volumes->saveVolume($volume))
		{
			Craft::$app->getSession()->setNotice(Craft::t('app', 'Volume saved.'));
			$this->redirectToPostedUrl();
		}
		else
		{
			Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save volume.'));
		}

		// Send the volume back to the template
		Craft::$app->getUrlManager()->setRouteParams([
			'volume' => $volume
		]);
	}

	/**
	 * Reorders asset volumes.
	 *
	 * @return null
	 */
	public function actionReorderVolumes()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$volumeIds = JsonHelper::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
		Craft::$app->volumes->reorderVolumes($volumeIds);

		$this->returnJson(['success' => true]);
	}

	/**
	 * Deletes an asset volume.
	 *
	 * @return null
	 */
	public function actionDeleteVolume()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$volumeId = Craft::$app->getRequest()->getRequiredBodyParam('id');

		Craft::$app->volumes->deleteVolumeById($volumeId);

		$this->returnJson(['success' => true]);
	}

	/**
	 * Load Assets VolumeType data.
	 *
	 * This is used to, for example, load Amazon S3 bucket list or Rackspace Cloud Storage Containers.
	 *
	 * @return null
	 */
	public function actionLoadVolumeTypeData()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$volumeType = Craft::$app->getRequest()->getRequiredBodyParam('volumeType');
		$dataType   = Craft::$app->getRequest()->getRequiredBodyParam('dataType');
		$params     = Craft::$app->getRequest()->getBodyParam('params');

		$volumeType = 'craft\app\assetvolumetypes\\'.$volumeType;

		if (!class_exists($volumeType))
		{
			$this->returnErrorJson(Craft::t('app', 'The volume type specified does not exist!'));
		}

		try
		{
			$result = call_user_func_array(array($volumeType, 'load'.ucfirst($dataType)), $params);
			$this->returnJson($result);
		}
		catch (\Exception $exception)
		{
			$this->returnErrorJson($exception->getMessage());
		}
	}
}