<?php

declare(strict_types=1);

use OCA\StructuredDiary\AppInfo\Application;
use OCA\StructuredDiary\Db\QuestionTypes;
use OCA\StructuredDiary\Db\TableNames;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This script can only be run from the command line.\n");
	exit(1);
}

$appRoot = dirname(__DIR__);
$nextcloudRoot = dirname($appRoot, 2);

if (!defined('OC_CONSOLE')) {
	define('OC_CONSOLE', 1);
}

require_once $nextcloudRoot . '/lib/base.php';
require_once $appRoot . '/vendor/autoload.php';

\OC_App::loadApp(Application::APP_ID);

$userId = $argv[1] ?? null;
if ($userId === null || $userId === '' || in_array($userId, ['-h', '--help'], true)) {
	fwrite(STDERR, "Usage: php scripts/seed_mood_observation_diary.php <user-id>\n");
	exit($userId === null || $userId === '' ? 1 : 0);
}

if (!\OC::$server->get(IUserManager::class)->userExists($userId)) {
	fwrite(STDERR, "User not found: {$userId}\n");
	exit(1);
}

/** @var IDBConnection $db */
$db = \OC::$server->get(IDBConnection::class);
$randomSeed = 20260516;
mt_srand($randomSeed);

$start = (new DateTimeImmutable('yesterday 00:00:00'))->modify('-729 days');
$changeDays = [
	90 => [
		'key' => 'mood_score',
		'label' => 'Mood score',
		'display_text' => 'How would I rate my overall mood tonight?',
		'type' => QuestionTypes::RATING,
		'minimum' => 0.0,
		'maximum' => 10.0,
		'choices' => null,
		'active' => true,
		'template_text' => 'Use the whole day, not just the last hour.',
	],
	180 => [
		'key' => 'sleep_quality',
		'label' => 'Sleep recovery',
		'display_text' => 'How restorative did last night feel?',
		'type' => QuestionTypes::RATING,
		'minimum' => 0.0,
		'maximum' => 10.0,
		'choices' => null,
		'active' => true,
		'template_text' => '0 is not restorative, 10 is deeply restorative.',
	],
	270 => [
		'key' => 'main_trigger',
		'label' => 'Main trigger',
		'display_text' => 'Which trigger was most noticeable today?',
		'type' => QuestionTypes::SELECT,
		'minimum' => null,
		'maximum' => null,
		'choices' => ['workload', 'conflict', 'loneliness', 'uncertainty', 'physical discomfort', 'none clear'],
		'active' => true,
		'template_text' => 'Pick the strongest driver, even if several were present.',
	],
	365 => [
		'key' => 'narrative',
		'label' => 'Observation note',
		'display_text' => 'What pattern did I notice in my mood today?',
		'type' => QuestionTypes::TEXT,
		'minimum' => 0.0,
		'maximum' => 900.0,
		'choices' => null,
		'active' => true,
		'template_text' => 'Stay observational and concrete.',
	],
	500 => [
		'key' => 'body_tension',
		'label' => 'Body tension count',
		'display_text' => 'How many noticeable tension episodes did I catch today?',
		'type' => QuestionTypes::INTEGER,
		'minimum' => 0.0,
		'maximum' => 12.0,
		'choices' => null,
		'active' => true,
		'template_text' => 'Count distinct moments rather than intensity.',
	],
	620 => [
		'key' => 'coping_action',
		'label' => 'Helpful response',
		'display_text' => 'Which response helped most today?',
		'type' => QuestionTypes::SELECT,
		'minimum' => null,
		'maximum' => null,
		'choices' => ['walk', 'breathing', 'early rest', 'talked it through', 'reduced plans', 'none'],
		'active' => true,
		'template_text' => 'Choose what changed the evening most.',
	],
];

$questionDefinitions = [
	[
		'key' => 'mood_score',
		'label' => 'Mood',
		'display_text' => 'How stable did my mood feel today?',
		'type' => QuestionTypes::RATING,
		'minimum' => 0.0,
		'maximum' => 10.0,
		'choices' => null,
		'active' => true,
		'template_text' => '0 is very low, 10 is very steady and bright.',
	],
	[
		'key' => 'anxiety_level',
		'label' => 'Anxiety',
		'display_text' => 'How loud was anxiety today?',
		'type' => QuestionTypes::RATING,
		'minimum' => 0.0,
		'maximum' => 10.0,
		'choices' => null,
		'active' => true,
		'template_text' => 'Rate the average level across the day.',
	],
	[
		'key' => 'energy_level',
		'label' => 'Energy',
		'display_text' => 'How much usable energy did I have?',
		'type' => QuestionTypes::RATING,
		'minimum' => 0.0,
		'maximum' => 10.0,
		'choices' => null,
		'active' => true,
		'template_text' => 'Usable means energy I could direct intentionally.',
	],
	[
		'key' => 'sleep_hours',
		'label' => 'Sleep hours',
		'display_text' => 'How many hours did I sleep last night?',
		'type' => QuestionTypes::NUMBER,
		'minimum' => 0.0,
		'maximum' => 14.0,
		'choices' => null,
		'active' => true,
		'template_text' => 'Estimate to the nearest quarter hour.',
	],
	[
		'key' => 'sleep_quality',
		'label' => 'Sleep quality',
		'display_text' => 'How was sleep quality on a 1 to 5 scale?',
		'type' => QuestionTypes::INTEGER,
		'minimum' => 1.0,
		'maximum' => 5.0,
		'choices' => null,
		'active' => true,
		'template_text' => '1 is poor, 5 is excellent.',
	],
	[
		'key' => 'moved_outside',
		'label' => 'Outside movement',
		'display_text' => 'Did I move outside today?',
		'type' => QuestionTypes::BOOLEAN,
		'minimum' => null,
		'maximum' => null,
		'choices' => null,
		'active' => true,
		'template_text' => 'Any deliberate walk, cycle, or outside errand counts.',
	],
	[
		'key' => 'bedtime',
		'label' => 'Bedtime',
		'display_text' => 'What time do I expect to go to bed?',
		'type' => QuestionTypes::TIME,
		'minimum' => null,
		'maximum' => null,
		'choices' => null,
		'active' => true,
		'template_text' => 'Use the planned time if the day is not finished.',
	],
	[
		'key' => 'dominant_mood',
		'label' => 'Dominant mood',
		'display_text' => 'Which mood was most present?',
		'type' => QuestionTypes::SELECT,
		'minimum' => null,
		'maximum' => null,
		'choices' => ['low', 'anxious', 'irritable', 'flat', 'steady', 'hopeful', 'content'],
		'active' => true,
		'template_text' => 'Choose the most persistent mood, not the strongest moment.',
	],
	[
		'key' => 'main_trigger',
		'label' => 'Trigger note',
		'display_text' => 'What seemed to influence mood most?',
		'type' => QuestionTypes::EDITABLE_SELECT,
		'minimum' => null,
		'maximum' => null,
		'choices' => ['work', 'sleep', 'family', 'health', 'money', 'weather', 'social contact'],
		'active' => true,
		'template_text' => 'Use a short phrase if no listed option fits.',
	],
	[
		'key' => 'narrative',
		'label' => 'Daily observation',
		'display_text' => 'What did I observe about my mood today?',
		'type' => QuestionTypes::TEXT,
		'minimum' => 0.0,
		'maximum' => 700.0,
		'choices' => null,
		'active' => true,
		'template_text' => 'Two or three sentences are enough.',
	],
	[
		'key' => 'body_tension',
		'label' => 'Body tension',
		'display_text' => 'How much body tension did I notice?',
		'type' => QuestionTypes::RATING,
		'minimum' => 0.0,
		'maximum' => 10.0,
		'choices' => null,
		'active' => true,
		'template_text' => 'Include jaw, shoulders, stomach, and breath.',
	],
	[
		'key' => 'coping_action',
		'label' => 'Coping action',
		'display_text' => 'Which response helped most?',
		'type' => QuestionTypes::SELECT,
		'minimum' => null,
		'maximum' => null,
		'choices' => ['walk', 'breathing', 'journaling', 'music', 'talked to someone', 'early night', 'none'],
		'active' => true,
		'template_text' => 'Pick the one that made the biggest difference.',
	],
];

function normalFloat(float $mean, float $stdDev): float {
	$u1 = max(mt_rand() / mt_getrandmax(), 0.000001);
	$u2 = mt_rand() / mt_getrandmax();

	return $mean + $stdDev * sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
}

function clampFloat(float $value, float $minimum, float $maximum): float {
	return max($minimum, min($maximum, $value));
}

function insertRow(IDBConnection $db, string $tableName, array $values): int {
	$qb = $db->getQueryBuilder();
	$qb->insert($tableName);
	foreach ($values as $column => $value) {
		$qb->setValue($column, $qb->createNamedParameter($value));
	}
	$qb->executeStatement();

	return (int)$db->lastInsertId($tableName);
}

function updateQuestionNextVersion(IDBConnection $db, int $questionId, int $nextVersionId): void {
	$qb = $db->getQueryBuilder();
	$qb->update(TableNames::QUESTIONS)
		->set('next_version_id', $qb->createNamedParameter($nextVersionId, IQueryBuilder::PARAM_INT))
		->where($qb->expr()->eq('id', $qb->createNamedParameter($questionId, IQueryBuilder::PARAM_INT)));
	$qb->executeStatement();
}

function updateAnswerNextVersion(IDBConnection $db, int $answerId, int $nextVersionId): void {
	$qb = $db->getQueryBuilder();
	$qb->update(TableNames::ANSWERS)
		->set('next_version_id', $qb->createNamedParameter($nextVersionId, IQueryBuilder::PARAM_INT))
		->where($qb->expr()->eq('id', $qb->createNamedParameter($answerId, IQueryBuilder::PARAM_INT)));
	$qb->executeStatement();
}

function activeQuestionFor(array $versionsByKey, string $key, int $timestamp): array {
	$active = $versionsByKey[$key][0];
	foreach ($versionsByKey[$key] as $version) {
		if ($version['created_at'] <= $timestamp) {
			$active = $version;
		}
	}

	return $active;
}

function addQuestionVersion(IDBConnection $db, int $diaryId, int $order, ?array $previous, array $definition, int $createdAt): array {
	$questionId = insertRow($db, TableNames::QUESTIONS, [
		'chain_id' => $previous['chain_id'] ?? 0,
		'diary_id' => $diaryId,
		'diary_question_order' => $order,
		'created_at' => $createdAt,
		'label' => $definition['label'],
		'display_text' => $definition['display_text'],
		'type' => $definition['type'],
		'minimum' => $definition['minimum'],
		'maximum' => $definition['maximum'],
		'json_choices' => $definition['choices'] === null ? null : json_encode($definition['choices'], JSON_THROW_ON_ERROR),
		'active' => $definition['active'] ? 1 : 0,
		'template_text' => $definition['template_text'],
		'previous_version_id' => $previous['id'] ?? null,
		'next_version_id' => null,
	]);

	$chainId = $previous['chain_id'] ?? $questionId;
	if ($previous === null) {
		$qb = $db->getQueryBuilder();
		$qb->update(TableNames::QUESTIONS)
			->set('chain_id', $qb->createNamedParameter($chainId, IQueryBuilder::PARAM_INT))
			->set('diary_question_order', $qb->createNamedParameter($order, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($questionId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	} else {
		updateQuestionNextVersion($db, $previous['id'], $questionId);
	}

	return [
		'id' => $questionId,
		'chain_id' => $chainId,
		'key' => $definition['key'],
		'type' => $definition['type'],
		'minimum' => $definition['minimum'],
		'maximum' => $definition['maximum'],
		'choices' => $definition['choices'],
		'created_at' => $createdAt,
	];
}

function moodState(int $day): array {
	$seasonal = sin(($day / 365.0) * 2.0 * M_PI) * 0.7;
	$slowWave = sin(($day / 47.0) * 2.0 * M_PI) * 0.9;
	$recovery = min(1.2, $day / 730.0 * 1.2);
	$mood = clampFloat(5.0 + $recovery + $seasonal + $slowWave + normalFloat(0.0, 0.9), 0.0, 10.0);
	$anxiety = clampFloat(7.0 - ($mood * 0.45) + normalFloat(0.0, 1.1), 0.0, 10.0);
	$energy = clampFloat(3.6 + ($mood * 0.38) + normalFloat(0.0, 1.0), 0.0, 10.0);
	$tension = clampFloat(2.0 + ($anxiety * 0.55) + normalFloat(0.0, 1.0), 0.0, 10.0);
	$sleepHours = clampFloat(6.4 + (($energy - 5.0) * 0.12) - (($anxiety - 5.0) * 0.16) + normalFloat(0.0, 0.8), 3.5, 9.5);

	return [
		'mood' => $mood,
		'anxiety' => $anxiety,
		'energy' => $energy,
		'tension' => $tension,
		'sleep_hours' => $sleepHours,
	];
}

function dominantMood(array $state): string {
	if ($state['mood'] < 3.2) {
		return 'low';
	}
	if ($state['anxiety'] > 6.8) {
		return 'anxious';
	}
	if ($state['tension'] > 7.0) {
		return 'irritable';
	}
	if ($state['energy'] < 3.4) {
		return 'flat';
	}
	if ($state['mood'] > 7.2) {
		return mt_rand(0, 1) === 0 ? 'hopeful' : 'content';
	}

	return 'steady';
}

function answerPayload(array $question, array $state, int $day): array {
	$mood = dominantMood($state);
	$triggerOptions = ['workload', 'conflict', 'loneliness', 'uncertainty', 'physical discomfort', 'none clear'];
	$editableTriggers = ['work', 'sleep', 'family', 'health', 'money', 'weather', 'social contact', 'too many plans'];
	$coping = ['walk', 'breathing', 'journaling', 'music', 'talked to someone', 'early night', 'none'];
	$copingLate = ['walk', 'breathing', 'early rest', 'talked it through', 'reduced plans', 'none'];

	switch ($question['key']) {
		case 'mood_score':
			return [null, round($state['mood'], 1)];

		case 'anxiety_level':
			return [null, round($state['anxiety'], 1)];

		case 'energy_level':
			return [null, round($state['energy'], 1)];

		case 'sleep_hours':
			return [null, round($state['sleep_hours'] * 4.0) / 4.0];

		case 'sleep_quality':
			if ($question['type'] === QuestionTypes::RATING) {
				return [null, round(clampFloat(($state['sleep_hours'] - 3.5) / 6.0 * 10.0 + normalFloat(0.0, 0.8), 0.0, 10.0), 1)];
			}

			return [null, (float)(int)clampFloat(round(($state['sleep_hours'] - 3.5) / 6.0 * 4.0 + 1.0), 1.0, 5.0)];

		case 'moved_outside':
			$probability = $state['energy'] > 5.0 ? 78 : 58;
			return [null, mt_rand(1, 100) <= $probability ? 1.0 : 0.0];

		case 'bedtime':
			$minutes = (int)round(clampFloat(normalFloat(22.75 * 60.0, 52.0), 20.5 * 60.0, 25.25 * 60.0));
			$minutes %= 24 * 60;

			return [sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60), null];

		case 'dominant_mood':
			return [$mood, null];

		case 'main_trigger':
			if ($question['type'] === QuestionTypes::SELECT) {
				$value = $triggerOptions[array_rand($triggerOptions)];
			} else {
				$value = $editableTriggers[array_rand($editableTriggers)];
			}

			return [$value, null];

		case 'narrative':
			$phrases = [
				'The day felt heavy early on, then settled after I reduced the number of open tasks.',
				'Mood moved in small waves rather than one dramatic swing. The evening check-in made the pattern easier to see.',
				'Anxiety was most visible in my shoulders and in how quickly I wanted to leave conversations.',
				'There was more steadiness after lunch. Sleep still seems to be the first signal when mood starts drifting.',
				'The strongest shift came after taking a walk and writing down what was actually urgent.',
				'I noticed less rumination than last month, though uncertainty still pulled attention in the evening.',
			];

			return [$phrases[($day + mt_rand(0, count($phrases) - 1)) % count($phrases)], null];

		case 'body_tension':
			if ($question['type'] === QuestionTypes::INTEGER) {
				return [null, (float)(int)round(clampFloat($state['tension'] / 10.0 * 12.0, 0.0, 12.0))];
			}

			return [null, round($state['tension'], 1)];

		case 'coping_action':
			$options = $question['choices'] === $copingLate ? $copingLate : $coping;

			return [$options[array_rand($options)], null];
	}

	return [null, null];
}

$db->beginTransaction();
try {
	$diaryId = insertRow($db, TableNames::DIARIES, [
		'user_id' => $userId,
		'title' => 'Mood Observation Log',
		'description' => 'Synthetic two-year diary for testing structured mood tracking, question versioning, and answer edits.',
		'reminder_active' => 1,
		'reminder_time' => 21 * 3600,
		'reminder_count' => 3,
		'reminder_delay' => 2700,
		'reminder_signal_first' => 'evening observation',
		'reminder_signal_repeat' => 'gentle follow-up',
		'entry_schedule' => 86400,
	]);

	$versionsByKey = [];
	foreach ($questionDefinitions as $index => $definition) {
		$versionsByKey[$definition['key']][] = addQuestionVersion($db, $diaryId, $index + 1, null, $definition, $start->getTimestamp());
	}

	foreach ($changeDays as $day => $definition) {
		$previous = $versionsByKey[$definition['key']][array_key_last($versionsByKey[$definition['key']])];
		$versionsByKey[$definition['key']][] = addQuestionVersion(
			$db,
			$diaryId,
			array_search($definition['key'], array_column($questionDefinitions, 'key'), true) + 1,
			$previous,
			$definition,
			$start->modify("+{$day} days")->setTime(9, 0)->getTimestamp(),
		);
	}

	$entryCount = 0;
	$answerCount = 0;
	$answerEditCount = 0;
	$nextEditDay = mt_rand(4, 6);
	for ($day = 0; $day < 730; $day++) {
		$isSkipped = mt_rand(1, 100) <= 7 || ($day > 0 && $day % 89 === 0);
		if ($isSkipped) {
			continue;
		}

		$minutesAfterMidnight = (int)round(clampFloat(normalFloat(21.0 * 60.0, 58.0), 18.25 * 60.0, 23.75 * 60.0));
		$entryTime = $start
			->modify("+{$day} days")
			->setTime(intdiv($minutesAfterMidnight, 60), $minutesAfterMidnight % 60, mt_rand(0, 59))
			->getTimestamp();

		$entryId = insertRow($db, TableNames::ENTRIES, [
			'diary_id' => $diaryId,
			'timestamp' => $entryTime,
			'title' => 'Mood observation ' . $start->modify("+{$day} days")->format('Y-m-d'),
		]);
		$entryCount++;

		$state = moodState($day);
		$entryAnswerIds = [];
		foreach ($questionDefinitions as $definition) {
			$question = activeQuestionFor($versionsByKey, $definition['key'], $entryTime);
			[$textContent, $numericContent] = answerPayload($question, $state, $day);
			$answerId = insertRow($db, TableNames::ANSWERS, [
				'diary_id' => $diaryId,
				'entry_id' => $entryId,
				'question_id' => $question['id'],
				'created_at' => $entryTime + mt_rand(30, 900),
				'text_content' => $textContent,
				'numeric_content' => $numericContent,
				'previous_version_id' => null,
				'next_version_id' => null,
			]);
			$entryAnswerIds[$definition['key']] = [$answerId, $question, $textContent, $numericContent];
			$answerCount++;
		}

		if ($day >= $nextEditDay) {
			$editKey = ['narrative', 'mood_score', 'anxiety_level', 'coping_action'][$answerEditCount % 4];
			[$previousAnswerId, $question, $textContent, $numericContent] = $entryAnswerIds[$editKey];
			if ($editKey === 'narrative') {
				$textContent .= ' Edited later: the first note understated how much the evening routine helped.';
			} elseif ($numericContent !== null) {
				$numericContent = round(clampFloat($numericContent + normalFloat(0.0, 0.4), 0.0, 10.0), 1);
			} elseif ($question['key'] === 'coping_action') {
				$textContent = in_array('breathing', $question['choices'] ?? [], true) ? 'breathing' : 'journaling';
			}
			$newAnswerId = insertRow($db, TableNames::ANSWERS, [
				'diary_id' => $diaryId,
				'entry_id' => $entryId,
				'question_id' => $question['id'],
				'created_at' => $entryTime + mt_rand(1200, 5400),
				'text_content' => $textContent,
				'numeric_content' => $numericContent,
				'previous_version_id' => $previousAnswerId,
				'next_version_id' => null,
			]);
			updateAnswerNextVersion($db, $previousAnswerId, $newAnswerId);
			$answerCount++;
			$answerEditCount++;
			$nextEditDay = $day + mt_rand(4, 6);
		}
	}

	$db->commit();
} catch (Throwable $e) {
	$db->rollBack();
	fwrite(STDERR, $e->getMessage() . "\n");
	exit(1);
}

echo "Created diary {$diaryId} for {$userId}\n";
echo "Entries: {$entryCount}\n";
echo "Questions: 12 current chains, 6 historical changes\n";
echo "Answers: {$answerCount} including {$answerEditCount} edited answer versions\n";
echo "Random seed: {$randomSeed}\n";
