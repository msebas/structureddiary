<?php

declare(strict_types=1);
?>

<div class="section" id="structureddiary-analysis-settings">
	<h2><?php p($l->t('Structured Diary analysis service')); ?></h2>
	<div
		id="structureddiary-analysis-settings-app"
		data-save-url="<?php p($_['save_url']); ?>"
		data-test-url="<?php p($_['test_url']); ?>"
		data-service-url="<?php p($_['service_url']); ?>"
		data-service-secret-configured="<?php p($_['service_secret_configured'] ? 'true' : 'false'); ?>"
		data-output-base-folder="<?php p($_['output_base_folder']); ?>"
		data-https-warning="<?php p($_['https_warning'] ? 'true' : 'false'); ?>">
	</div>
</div>
