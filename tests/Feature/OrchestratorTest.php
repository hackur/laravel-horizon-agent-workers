<?php

namespace Tests\Feature;

use App\Jobs\LLM\OpenClaw\OpenClawJob;
use App\Jobs\LLM\OpenClaw\OrchestratorJob;
use App\Models\AgentRun;
use App\Models\AgentReview;
use App\Models\AgentOutput;
use App\Services\AgentOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_openclaw_job_can_be_dispatched(): void
    {
        OpenClawJob::dispatch('Say hello', 'sonnet');

        Queue::assertPushed(OpenClawJob::class, function ($job) {
            return true;
        });
    }

    public function test_orchestrator_job_can_be_dispatched(): void
    {
        OrchestratorJob::dispatch(
            task: 'Refactor the code',
            workingDirectory: '/tmp',
            options: [
                'agent_model' => 'sonnet',
                'reviewer_model' => 'opus',
            ]
        );

        Queue::assertPushed(OrchestratorJob::class);
    }

    public function test_agent_run_model_can_be_created(): void
    {
        $run = AgentRun::create([
            'task' => 'Test task',
            'working_directory' => '/tmp',
            'agent_model' => 'sonnet',
            'reviewer_model' => 'opus',
            'max_iterations' => 3,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->assertDatabaseHas('agent_runs', [
            'id' => $run->id,
            'task' => 'Test task',
            'status' => 'running',
        ]);
    }

    public function test_agent_run_has_reviews_relationship(): void
    {
        $run = AgentRun::create([
            'task' => 'Test task',
            'working_directory' => '/tmp',
            'agent_model' => 'sonnet',
            'reviewer_model' => 'opus',
            'max_iterations' => 3,
            'status' => 'running',
            'started_at' => now(),
        ]);

        AgentReview::create([
            'agent_run_id' => $run->id,
            'iteration' => 1,
            'approved' => false,
            'feedback' => 'Needs work',
            'score' => 5,
            'model' => 'opus',
        ]);

        $this->assertCount(1, $run->reviews);
        $this->assertEquals('Needs work', $run->reviews->first()->feedback);
    }

    public function test_agent_run_has_outputs_relationship(): void
    {
        $run = AgentRun::create([
            'task' => 'Test task',
            'working_directory' => '/tmp',
            'agent_model' => 'sonnet',
            'reviewer_model' => 'opus',
            'max_iterations' => 3,
            'status' => 'running',
            'started_at' => now(),
        ]);

        AgentOutput::create([
            'agent_run_id' => $run->id,
            'iteration' => 1,
            'type' => 'agent',
            'content' => 'Here is my work...',
            'model' => 'sonnet',
        ]);

        $this->assertCount(1, $run->outputs);
        $this->assertEquals('agent', $run->outputs->first()->type);
    }

    public function test_orchestrator_parses_approved_json_response(): void
    {
        $orchestrator = new AgentOrchestrator();
        
        $reflection = new \ReflectionMethod($orchestrator, 'parseReviewOutput');
        $reflection->setAccessible(true);

        $output = <<<JSON
Here is my review:

```json
{
    "approved": true,
    "score": 9,
    "feedback": "Excellent work! Code is clean and well-structured."
}
```
JSON;

        $result = $reflection->invoke($orchestrator, $output);

        $this->assertTrue($result['approved']);
        $this->assertEquals(9, $result['score']);
        $this->assertStringContainsString('Excellent', $result['feedback']);
    }

    public function test_orchestrator_parses_rejected_json_response(): void
    {
        $orchestrator = new AgentOrchestrator();
        
        $reflection = new \ReflectionMethod($orchestrator, 'parseReviewOutput');
        $reflection->setAccessible(true);

        $output = <<<JSON
```json
{
    "approved": false,
    "score": 4,
    "feedback": "Missing error handling. Add try-catch blocks."
}
```
JSON;

        $result = $reflection->invoke($orchestrator, $output);

        $this->assertFalse($result['approved']);
        $this->assertEquals(4, $result['score']);
        $this->assertStringContainsString('error handling', $result['feedback']);
    }

    public function test_orchestrator_handles_malformed_json_gracefully(): void
    {
        $orchestrator = new AgentOrchestrator();
        
        $reflection = new \ReflectionMethod($orchestrator, 'parseReviewOutput');
        $reflection->setAccessible(true);

        $output = "LGTM! This looks good to me.";

        $result = $reflection->invoke($orchestrator, $output);

        // Should detect approval keywords
        $this->assertTrue($result['approved']);
    }

    public function test_orchestrator_builds_correct_agent_prompt_first_iteration(): void
    {
        $orchestrator = new AgentOrchestrator();
        
        $reflection = new \ReflectionMethod($orchestrator, 'buildAgentPrompt');
        $reflection->setAccessible(true);

        $task = 'Write a hello world function';
        $result = $reflection->invoke($orchestrator, $task, '', 1);

        $this->assertEquals($task, $result);
    }

    public function test_orchestrator_builds_correct_agent_prompt_with_feedback(): void
    {
        $orchestrator = new AgentOrchestrator();
        
        $reflection = new \ReflectionMethod($orchestrator, 'buildAgentPrompt');
        $reflection->setAccessible(true);

        $task = 'Write a hello world function';
        $feedback = 'Add error handling';
        $result = $reflection->invoke($orchestrator, $task, $feedback, 2);

        $this->assertStringContainsString($task, $result);
        $this->assertStringContainsString($feedback, $result);
        $this->assertStringContainsString('Feedback', $result);
    }

    public function test_orchestrator_builds_openclaw_command(): void
    {
        $orchestrator = new AgentOrchestrator();
        
        $reflection = new \ReflectionMethod($orchestrator, 'buildOpenClawCommand');
        $reflection->setAccessible(true);

        $command = $reflection->invoke($orchestrator, 'Hello', 'sonnet', null, false);

        $this->assertStringContainsString('openclaw', $command);
        $this->assertStringContainsString('agent', $command);
        $this->assertStringContainsString('--local', $command);
        $this->assertStringContainsString('--json', $command);
        $this->assertStringContainsString('--session-id', $command);
        $this->assertStringContainsString('-m', $command);
    }

    public function test_orchestrator_adds_thinking_for_reviewer(): void
    {
        $orchestrator = new AgentOrchestrator();
        
        $reflection = new \ReflectionMethod($orchestrator, 'buildOpenClawCommand');
        $reflection->setAccessible(true);

        $command = $reflection->invoke($orchestrator, 'Review this', 'opus', null, true);

        $this->assertStringContainsString('--thinking', $command);
    }

    public function test_orchestrator_parses_openclaw_json_response(): void
    {
        $orchestrator = new AgentOrchestrator();
        
        $reflection = new \ReflectionMethod($orchestrator, 'parseOpenClawResponse');
        $reflection->setAccessible(true);

        $jsonResponse = json_encode([
            'payloads' => [
                ['text' => 'Hello from OpenClaw!', 'mediaUrl' => null]
            ],
            'meta' => ['durationMs' => 1234]
        ]);

        $result = $reflection->invoke($orchestrator, $jsonResponse);

        $this->assertEquals('Hello from OpenClaw!', $result);
    }

    public function test_orchestrator_handles_plain_text_response(): void
    {
        $orchestrator = new AgentOrchestrator();
        
        $reflection = new \ReflectionMethod($orchestrator, 'parseOpenClawResponse');
        $reflection->setAccessible(true);

        $plainText = 'Just plain text response';

        $result = $reflection->invoke($orchestrator, $plainText);

        $this->assertEquals($plainText, $result);
    }

    public function test_agent_run_status_helpers(): void
    {
        $run = new AgentRun(['status' => 'running']);
        $this->assertTrue($run->isRunning());
        $this->assertFalse($run->isCompleted());
        $this->assertFalse($run->isFailed());

        $run->status = 'completed';
        $this->assertFalse($run->isRunning());
        $this->assertTrue($run->isCompleted());

        $run->status = 'failed';
        $this->assertTrue($run->isFailed());
    }

    public function test_agent_run_duration_calculation(): void
    {
        $run = new AgentRun([
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $this->assertEqualsWithDelta(300, $run->duration, 5);
    }

    public function test_agent_review_status_label(): void
    {
        $review = new AgentReview(['approved' => true]);
        $this->assertStringContainsString('Approved', $review->status_label);

        $review->approved = false;
        $this->assertStringContainsString('Changes', $review->status_label);
    }
}
