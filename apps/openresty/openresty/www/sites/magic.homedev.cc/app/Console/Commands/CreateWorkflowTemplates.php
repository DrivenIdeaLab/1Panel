<?php

namespace App\Console\Commands;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateWorkflowTemplates extends Command
{
    protected $signature = 'workflows:create-templates';
    protected $description = 'Create workflow template catalogue';

    public function handle()
    {
        $this->info('Creating workflow templates catalogue...');
        $this->newLine();

        $userId = 1; // Admin user

        $templates = $this->getTemplates();

        $bar = $this->output->createProgressBar(count($templates));
        $bar->start();

        foreach ($templates as $templateData) {
            $steps = $templateData['steps'];
            unset($templateData['steps']);

            $templateData['user_id'] = $userId;
            $templateData['uuid'] = (string) Str::uuid();
            $templateData['version'] = '1.0.0';
            $templateData['execution_count'] = 0;
            $templateData['avg_execution_time'] = 0;
            $templateData['config'] = json_encode([]);
            $templateData['metadata'] = json_encode([]);

            $workflow = Workflow::create($templateData);

            foreach ($steps as $stepData) {
                $promptTemplate = $stepData['config']['prompt'] ?? '';

                $stepData['workflow_id'] = $workflow->id;
                $stepData['uuid'] = (string) Str::uuid();
                $stepData['step_order'] = $stepData['order'];
                unset($stepData['order']);
                $stepData['prompt_template'] = $promptTemplate;
                $stepData['system_prompt'] = '';
                $stepData['engine_id'] = null;
                $stepData['input_mapping'] = json_encode([]);
                $stepData['output_key'] = 'result';
                $stepData['condition_script'] = null;
                $stepData['error_handling'] = json_encode(['retry' => 3, 'fallback' => null]);
                $stepData['config'] = json_encode($stepData['config']);
                $stepData['status'] = 'active';

                WorkflowStep::create($stepData);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('✅ Successfully created ' . count($templates) . ' workflow templates!');
        $this->newLine();
        $this->line('Templates available at: https://magic.homedev.cc/dashboard/user/workflows/templates');

        return Command::SUCCESS;
    }

    private function getTemplates(): array
    {
        return [
            [
                'name' => 'Blog Post Creation Pipeline',
                'description' => 'Complete workflow for creating SEO-optimized blog posts from topic to final article',
                'category' => 'Content Creation',
                'visibility' => 'public',
                'is_template' => true,
                'status' => 'active',
                'steps' => [
                    ['name' => 'Generate Blog Title Ideas', 'type' => 'text', 'order' => 1, 'config' => ['prompt' => 'Generate 5 engaging blog post titles about: {{topic}}']],
                    ['name' => 'Create Blog Outline', 'type' => 'text', 'order' => 2, 'config' => ['prompt' => 'Create detailed outline for: {{selected_title}}']],
                    ['name' => 'Write Introduction', 'type' => 'text', 'order' => 3, 'config' => ['prompt' => 'Write engaging introduction for: {{selected_title}}']],
                    ['name' => 'Write Main Content', 'type' => 'text', 'order' => 4, 'config' => ['prompt' => 'Write main content following: {{outline}}']],
                    ['name' => 'Generate Meta Description', 'type' => 'text', 'order' => 5, 'config' => ['prompt' => 'Write SEO meta description (155 chars) for: {{selected_title}}']],
                    ['name' => 'Create Call-to-Action', 'type' => 'text', 'order' => 6, 'config' => ['prompt' => 'Create compelling CTA conclusion']],
                ]
            ],
            [
                'name' => 'Product Description Generator',
                'description' => 'Transform product specs into compelling, conversion-focused descriptions',
                'category' => 'E-commerce',
                'visibility' => 'public',
                'is_template' => true,
                'status' => 'active',
                'steps' => [
                    ['name' => 'Extract Key Features', 'type' => 'text', 'order' => 1, 'config' => ['prompt' => 'Extract key features from: {{product_specs}}']],
                    ['name' => 'Write Main Description', 'type' => 'text', 'order' => 2, 'config' => ['prompt' => 'Write compelling description highlighting: {{key_features}}']],
                    ['name' => 'Generate Benefits List', 'type' => 'text', 'order' => 3, 'config' => ['prompt' => 'Convert features to benefits: {{key_features}}']],
                    ['name' => 'Create Product Title', 'type' => 'text', 'order' => 4, 'config' => ['prompt' => 'Create SEO title (60 chars): {{product_name}}']],
                ]
            ],
            [
                'name' => 'Email Marketing Campaign',
                'description' => 'Create complete email campaigns with subject lines, preview text, and body copy',
                'category' => 'Marketing',
                'visibility' => 'public',
                'is_template' => true,
                'status' => 'active',
                'steps' => [
                    ['name' => 'Generate Subject Lines', 'type' => 'text', 'order' => 1, 'config' => ['prompt' => 'Generate 5 email subject lines for: {{campaign_goal}}']],
                    ['name' => 'Write Preview Text', 'type' => 'text', 'order' => 2, 'config' => ['prompt' => 'Write preview text (50 chars) for: {{selected_subject}}']],
                    ['name' => 'Create Email Body', 'type' => 'text', 'order' => 3, 'config' => ['prompt' => 'Write persuasive email body for: {{campaign_goal}}']],
                    ['name' => 'Generate CTA Options', 'type' => 'text', 'order' => 4, 'config' => ['prompt' => 'Create 3 CTA button options for: {{campaign_goal}}']],
                ]
            ],
            [
                'name' => 'Social Media Content Calendar',
                'description' => 'Generate a week of social media posts across multiple platforms',
                'category' => 'Social Media',
                'visibility' => 'public',
                'is_template' => true,
                'status' => 'active',
                'steps' => [
                    ['name' => 'Generate Post Topics', 'type' => 'text', 'order' => 1, 'config' => ['prompt' => 'Generate 7 post topics for: {{brand_topic}}']],
                    ['name' => 'Write Instagram Captions', 'type' => 'text', 'order' => 2, 'config' => ['prompt' => 'Write Instagram captions for: {{topics}}']],
                    ['name' => 'Write Twitter/X Posts', 'type' => 'text', 'order' => 3, 'config' => ['prompt' => 'Convert to Twitter posts (280 chars): {{topics}}']],
                    ['name' => 'Generate Hashtags', 'type' => 'text', 'order' => 4, 'config' => ['prompt' => 'Generate relevant hashtags for: {{brand_topic}}']],
                    ['name' => 'Create LinkedIn Posts', 'type' => 'text', 'order' => 5, 'config' => ['prompt' => 'Convert to professional LinkedIn posts: {{topics}}']],
                ]
            ],
            [
                'name' => 'SEO Content Optimization',
                'description' => 'Optimize content for search engines with keyword research and meta tags',
                'category' => 'SEO',
                'visibility' => 'public',
                'is_template' => true,
                'status' => 'active',
                'steps' => [
                    ['name' => 'Analyze Current Content', 'type' => 'text', 'order' => 1, 'config' => ['prompt' => 'Analyze SEO issues in: {{content}}']],
                    ['name' => 'Generate Keywords', 'type' => 'text', 'order' => 2, 'config' => ['prompt' => 'Generate keywords for: {{topic}}']],
                    ['name' => 'Optimize Title Tag', 'type' => 'text', 'order' => 3, 'config' => ['prompt' => 'Create SEO title (60 chars) with: {{keywords}}']],
                    ['name' => 'Write Meta Description', 'type' => 'text', 'order' => 4, 'config' => ['prompt' => 'Write meta description (155 chars) with: {{keywords}}']],
                    ['name' => 'Improve Content', 'type' => 'text', 'order' => 5, 'config' => ['prompt' => 'Rewrite incorporating: {{keywords}}, {{content}}']],
                ]
            ],
            [
                'name' => 'Business Proposal Generator',
                'description' => 'Create professional business proposals from summary to pricing',
                'category' => 'Business Writing',
                'visibility' => 'public',
                'is_template' => true,
                'status' => 'active',
                'steps' => [
                    ['name' => 'Write Executive Summary', 'type' => 'text', 'order' => 1, 'config' => ['prompt' => 'Write executive summary for: {{project_description}}']],
                    ['name' => 'Define Project Scope', 'type' => 'text', 'order' => 2, 'config' => ['prompt' => 'Define project scope for: {{project_description}}']],
                    ['name' => 'Create Timeline', 'type' => 'text', 'order' => 3, 'config' => ['prompt' => 'Create timeline and milestones for: {{project_scope}}']],
                    ['name' => 'Write Deliverables', 'type' => 'text', 'order' => 4, 'config' => ['prompt' => 'List detailed deliverables for: {{project_scope}}']],
                    ['name' => 'Draft Terms & Conditions', 'type' => 'text', 'order' => 5, 'config' => ['prompt' => 'Draft terms for: {{project_description}}']],
                ]
            ],
            [
                'name' => 'Press Release Writer',
                'description' => 'Create newsworthy press releases with headline, body, and boilerplate',
                'category' => 'Public Relations',
                'visibility' => 'public',
                'is_template' => true,
                'status' => 'active',
                'steps' => [
                    ['name' => 'Generate Headlines', 'type' => 'text', 'order' => 1, 'config' => ['prompt' => 'Generate 5 press release headlines for: {{announcement}}']],
                    ['name' => 'Write Opening Paragraph', 'type' => 'text', 'order' => 2, 'config' => ['prompt' => 'Write opening paragraph (5Ws) for: {{announcement}}']],
                    ['name' => 'Develop Body Content', 'type' => 'text', 'order' => 3, 'config' => ['prompt' => 'Write detailed press release body for: {{announcement}}']],
                    ['name' => 'Add Executive Quotes', 'type' => 'text', 'order' => 4, 'config' => ['prompt' => 'Generate 2-3 executive quotes for: {{announcement}}']],
                    ['name' => 'Write Company Boilerplate', 'type' => 'text', 'order' => 5, 'config' => ['prompt' => 'Write company boilerplate (100 words) for: {{company_name}}']],
                ]
            ],
            [
                'name' => 'Story Development Workflow',
                'description' => 'Develop complete stories from concept to final draft',
                'category' => 'Creative Writing',
                'visibility' => 'public',
                'is_template' => true,
                'status' => 'active',
                'steps' => [
                    ['name' => 'Brainstorm Story Ideas', 'type' => 'text', 'order' => 1, 'config' => ['prompt' => 'Generate 5 story ideas in {{genre}} genre']],
                    ['name' => 'Develop Main Characters', 'type' => 'text', 'order' => 2, 'config' => ['prompt' => 'Create character profiles for: {{selected_story}}']],
                    ['name' => 'Create Plot Outline', 'type' => 'text', 'order' => 3, 'config' => ['prompt' => 'Outline 3-act structure for: {{selected_story}}']],
                    ['name' => 'Write Opening Scene', 'type' => 'text', 'order' => 4, 'config' => ['prompt' => 'Write opening scene for: {{selected_story}}']],
                    ['name' => 'Develop Middle Sections', 'type' => 'text', 'order' => 5, 'config' => ['prompt' => 'Write middle sections following: {{plot_outline}}']],
                    ['name' => 'Write Conclusion', 'type' => 'text', 'order' => 6, 'config' => ['prompt' => 'Write conclusion for: {{selected_story}}']],
                ]
            ],
            [
                'name' => 'YouTube Video Script',
                'description' => 'Create complete YouTube video scripts with hook, content, and CTA',
                'category' => 'Video Content',
                'visibility' => 'public',
                'is_template' => true,
                'status' => 'active',
                'steps' => [
                    ['name' => 'Generate Video Titles', 'type' => 'text', 'order' => 1, 'config' => ['prompt' => 'Generate 5 clickable YouTube titles about: {{video_topic}}']],
                    ['name' => 'Write Hook (First 10 sec)', 'type' => 'text', 'order' => 2, 'config' => ['prompt' => 'Write attention-grabbing 10-second hook for: {{selected_title}}']],
                    ['name' => 'Create Main Script', 'type' => 'text', 'order' => 3, 'config' => ['prompt' => 'Write detailed video script for: {{selected_title}}']],
                    ['name' => 'Add B-Roll Suggestions', 'type' => 'text', 'order' => 4, 'config' => ['prompt' => 'Suggest B-roll footage ideas for: {{script}}']],
                    ['name' => 'Write Video Description', 'type' => 'text', 'order' => 5, 'config' => ['prompt' => 'Write YouTube description with timestamps for: {{selected_title}}']],
                    ['name' => 'Generate Tags', 'type' => 'text', 'order' => 6, 'config' => ['prompt' => 'Generate 20 relevant YouTube tags for: {{selected_title}}']],
                ]
            ],
            [
                'name' => 'Competitive Analysis Report',
                'description' => 'Analyze competitors and generate comprehensive intelligence reports',
                'category' => 'Business Analysis',
                'visibility' => 'public',
                'is_template' => true,
                'status' => 'active',
                'steps' => [
                    ['name' => 'Identify Key Competitors', 'type' => 'text', 'order' => 1, 'config' => ['prompt' => 'List main competitors for: {{company_industry}}']],
                    ['name' => 'Analyze Strengths', 'type' => 'text', 'order' => 2, 'config' => ['prompt' => 'Analyze competitor strengths: {{competitors}}']],
                    ['name' => 'Analyze Weaknesses', 'type' => 'text', 'order' => 3, 'config' => ['prompt' => 'Analyze competitor weaknesses: {{competitors}}']],
                    ['name' => 'Market Positioning', 'type' => 'text', 'order' => 4, 'config' => ['prompt' => 'Analyze market positioning of: {{competitors}}']],
                    ['name' => 'Generate Recommendations', 'type' => 'text', 'order' => 5, 'config' => ['prompt' => 'Generate strategic recommendations based on analysis']],
                ]
            ],
            [
                'name' => 'Multi-Language Localization',
                'description' => 'Translate and localize content for multiple markets with cultural adaptation',
                'category' => 'Translation',
                'visibility' => 'public',
                'is_template' => true,
                'status' => 'active',
                'steps' => [
                    ['name' => 'Translate Content', 'type' => 'text', 'order' => 1, 'config' => ['prompt' => 'Translate to {{target_language}}: {{content}}']],
                    ['name' => 'Localize Cultural References', 'type' => 'text', 'order' => 2, 'config' => ['prompt' => 'Adapt cultural references for {{target_market}}: {{translated_content}}']],
                    ['name' => 'Adjust Tone and Style', 'type' => 'text', 'order' => 3, 'config' => ['prompt' => 'Adjust tone for {{target_market}}: {{localized_content}}']],
                    ['name' => 'Review and Polish', 'type' => 'text', 'order' => 4, 'config' => ['prompt' => 'Final review for {{target_language}}: {{adjusted_content}}']],
                ]
            ],
            [
                'name' => 'Customer Support Response Generator',
                'description' => 'Generate professional, empathetic customer support responses',
                'category' => 'Customer Support',
                'visibility' => 'public',
                'is_template' => true,
                'status' => 'active',
                'steps' => [
                    ['name' => 'Analyze Customer Issue', 'type' => 'text', 'order' => 1, 'config' => ['prompt' => 'Analyze and categorize issue: {{customer_message}}']],
                    ['name' => 'Generate Empathetic Opening', 'type' => 'text', 'order' => 2, 'config' => ['prompt' => 'Write empathetic opening for: {{issue_category}}']],
                    ['name' => 'Provide Solution', 'type' => 'text', 'order' => 3, 'config' => ['prompt' => 'Provide clear solution steps for: {{customer_issue}}']],
                    ['name' => 'Add Follow-up Actions', 'type' => 'text', 'order' => 4, 'config' => ['prompt' => 'Add appropriate follow-up actions for: {{solution}}']],
                ]
            ],
        ];
    }
}
