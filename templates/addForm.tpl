{**
 * plugins/generic/fixssl/settingsForm.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Google Analytics plugin settings
 *
 *}
<script>
	$(function() {ldelim}
	// Attach the form handler.
	$('#gaSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

	{rdelim});
	// $('#hey').click(() => console.log({json_encode($webhooks)}));
	// $('.remove-webhook').click(function(){ldelim}
	// const rawid = this.id;
	// const id = rawid.replace(/-removeButton/g, '');
	// const webhooks = Object.entries({json_encode($webhooks)});
	// const newWebhooks = webhooks.filter(([webhook, settings]) => webhook !== id);
	// console.log(newWebhooks)


	// {rdelim});
</script>

<style>
	:root {
		--slate100: #f1f5f9;
	}

	.options-row {
		display: flex;
		gap: 1rem;
	}

	.options-row li::marker {
		color: transparent;
	}

	.options-row li label {
		display: flex;
		padding: .5rem;
		flex-wrap: wrap;
		flex-direction: column-reverse;
		font-size: .9rem;
		align-items: center;
		height: 6rem;
		justify-content: space-between;
		text-align: center;
	}

	.options-row li label:hover {
		background-color: var(--slate100);

	}

	.options-row li label input {
		width: 1.5rem;
		height: 1.5rem;

	}

	.existing-webhook {
		border: 1px solid var(--slate100);
		border-radius: .5rem;
		margin-top: 1rem;
		margin-bottom: 1rem;
		padding: 1rem;
	}
</style>

<div>
	<form class="pkp_form" id="gaSettingsForm" method="post"
		action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
		{csrf}
		{include file="controllers/notification/inPlaceNotification.tpl" notificationId="gaSettingsFormNotification"}

		<div id="description">{translate key="plugins.generic.fixssl.manager.settings.description"}</div>

		{fbvFormArea id="webFeedSettingsFormArea"}
		{* {fbvElement type="text" id="fixsslSiteId" value=$fixsslSiteId label="plugins.generic.fixssl.manager.settings.fixsslSiteId"} *}
		<div>
			{fbvElement type="text" id="newWebhookUrl" required=true value=$newWebhookUrl label="plugins.generic.fixssl.manager.settings.newWebhookUrl"}
			<div class="options-row">
				{fbvElement type="checkbox" id="pubEdit" value=$newPubEdit label="plugins.generic.fixssl.manager.settings.pubEdit"}
				{fbvElement type="checkbox" id="addSubmission" value=$newAddSubmission label="plugins.generic.fixssl.manager.settings.addSubmission"}
				{fbvElement type="checkbox" id="editorAction" value=$newEditorAction label="plugins.generic.fixssl.manager.settings.editorAction"}
				{fbvElement type="checkbox" id="publicationPublished" value=$newPublicationPublished label="plugins.generic.fixssl.manager.settings.publicationPublished"}
				{fbvElement type="checkbox" id="confirmReview" value=$newConfirmReview label="plugins.generic.fixssl.manager.settings.confirmReview"}
				{fbvElement type="checkbox" id="submissionStatus" value=$newSubmissionStatus label="plugins.generic.fixssl.manager.settings.submissionStatus"}
			</div>
		</div>

		{/fbvFormArea}

		{fbvFormButtons submitText="plugins.generic.fixssl.manager.addWebhook.button"}

		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	</form>
	<form class="pkp_form" id="gaSettingsForm" method="post"
		action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
		{csrf}
		{include file="controllers/notification/inPlaceNotification.tpl" notificationId="gaSettingsFormNotification"}

		<div id="description">{translate key="plugins.generic.fixssl.manager.settings.description"}</div>

		{fbvFormArea id="webFeedSettingsFormArea"}
		{* {fbvElement type="text" id="fixsslSiteId" value=$fixsslSiteId label="plugins.generic.fixssl.manager.settings.fixsslSiteId"} *}
		<div>
			{fbvElement type="text" id="newWebhookUrl" required=true value=$newWebhookUrl label="plugins.generic.fixssl.manager.settings.newWebhookUrl"}
			<div class="options-row">
				{fbvElement type="checkbox" id="pubEdit" value=$newPubEdit label="plugins.generic.fixssl.manager.settings.pubEdit"}
				{fbvElement type="checkbox" id="addSubmission" value=$newAddSubmission label="plugins.generic.fixssl.manager.settings.addSubmission"}
				{fbvElement type="checkbox" id="editorAction" value=$newEditorAction label="plugins.generic.fixssl.manager.settings.editorAction"}
				{fbvElement type="checkbox" id="publicationPublished" value=$newPublicationPublished label="plugins.generic.fixssl.manager.settings.publicationPublished"}
				{fbvElement type="checkbox" id="confirmReview" value=$newConfirmReview label="plugins.generic.fixssl.manager.settings.confirmReview"}
				{fbvElement type="checkbox" id="submissionStatus" value=$newSubmissionStatus label="plugins.generic.fixssl.manager.settings.submissionStatus"}
			</div>
		</div>

		{/fbvFormArea}

		{fbvFormButtons submitText="plugins.generic.fixssl.manager.addWebhook.button"}

		<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	</form>
</div>