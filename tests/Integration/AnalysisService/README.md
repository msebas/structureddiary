# Analysis service system test

This directory contains the opt-in system-test fixture for the real Python
analysis-service image. It is deliberately separate from the regular
integration suite: the normal suite must not start containers or make network
requests.

Run it against a disposable Nextcloud instance:

```bash
ANALYSIS_SERVICE_SECRET=replace-with-test-secret \
bash tests/bin/test_analysis_service_integration.sh
```

`structureddiary-analysis:local` is the default image. Override
`ANALYSIS_SERVICE_IMAGE` when needed. Every run creates a new bind-mounted
execution directory, starts a new service container, and prints the retained
directory path after the container is removed. Set `ANALYSIS_SERVICE_WORK_DIR`
to choose that directory explicitly. If the `ANALYSIS_SERVICE_SECRET` is not 
set a random one is generated.

The default callback URL is `http://nextcloud_integration_tests`, which is the
Docker-network alias of the PHP integration-test container. Override
`NEXTCLOUD_SYSTEM_TEST_BASE_URL` and `NEXTCLOUD_DOCKER_NETWORK` for another
setup. The runner temporarily configures that URL and trusted domain on the
integration container, then restores both values after the test.

The runner passes optional LocalAI configuration through to the service:

```text
LOCALAI_BASE_URL
LOCALAI_MODEL
LOCALAI_API_KEY
```

## Intended workflow

1. Start the Compose service with `ANALYSIS_SERVICE_IMAGE`.
2. Configure the app's analysis-service URL and secret through the admin API.
   Saving settings must register the Nextcloud base URL and generated API token
   with the Python service.
3. Verify the Python service healthcheck through the app admin endpoint.
4. Create an analysis job with `start=true` and `includeTextAnalysis=false`.
   No LLM endpoint is called by this workflow. Record its UUID and job token.
5. Wait for the Python service to poll and acknowledge the job while it drives
   the expected state progression through `READY_QUEUE`, `QUEUED`, `LOAD_DATA`,
   `RUNNING`, `WORKER_UPLOAD`, and `JOB_COMPLETED`.
6. Verify that the service creates the artifact manifest, uploads at least a
   JSON and HTML artifact, and calls the service finalization endpoint.
7. Poll the user API until the job is `COMPLETED`; verify every artifact has a
   Nextcloud file ID, checksum, and downloaded flag. The service's cleanup
   acknowledgement is intentionally not asserted until its deletion workflow is
   implemented.
8. Assert that each recorded artifact has a Nextcloud file ID, checksum, and
   uploaded flag.

The test must use a fixture report generated without text analysis. It must not
configure an LLM or make assertions that require an external model.
