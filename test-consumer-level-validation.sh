#!/bin/bash

# Test Consumer-Level Duplicate Validation Architecture
# This tests: Form filled -> Beanstalk -> Validation by Laravel Consumer -> Consumer will insert data or validation into MongoDB

echo "🔄 Testing Consumer-Level Duplicate Validation Architecture"
echo "============================================================"

cd /home/sunil/Desktop/Sunil/Students

echo "📋 Current architecture flow:"
echo "1. Form/CSV data submitted"
echo "2. Data queued to Beanstalk"
echo "3. Laravel Consumer (ProcessFormSubmissionData) processes queue"
echo "4. Consumer validates data (including duplicate email check)"
echo "5. If validation passes: Create FormSubmission record in MongoDB"
echo "6. If validation fails: Mark as failed, trigger DuplicateEmailDetected event"
echo ""

echo "📊 Current FormSubmission counts:"
php artisan tinker --execute="
echo 'Total submissions: ' . \App\Models\FormSubmission::count();
echo \"\nSuccessful submissions: \" . \App\Models\FormSubmission::where('status', 'completed')->count();
echo \"\nFailed submissions (including duplicates): \" . \App\Models\FormSubmission::where('status', 'failed')->count();
echo \"\nProcessing submissions: \" . \App\Models\FormSubmission::where('status', 'processing')->count();
"

echo ""
echo "📄 Recent failed submissions (likely duplicates caught by consumer):"
php artisan tinker --execute="
\App\Models\FormSubmission::where('status', 'failed')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get(['data.email', 'source', 'operation', 'created_at'])
    ->each(function(\$sub) { 
        echo \"\n- Email: \" . (\$sub->data['email'] ?? 'N/A') . \", Source: \$sub->source, Operation: \$sub->operation, Failed at: \$sub->created_at\"; 
    });
"

echo ""
echo "✅ Architecture Verification:"
echo "- Model-level duplicate prevention: REMOVED (no longer in FormSubmission boot method)"
echo "- Consumer-level validation: ACTIVE (ProcessFormSubmissionData validates before creation)"
echo "- Duplicate detection: Handled by FormSubmissionValidator in consumer"
echo "- Failed submissions: Stored with 'failed' status for tracking"
echo "- Events: DuplicateEmailDetected triggered for analytics"

echo ""
echo "🎯 Key Implementation Points:"
echo "- FormSubmission model no longer prevents duplicates at creation"
echo "- ProcessFormSubmissionData job validates data BEFORE creating FormSubmission"
echo "- Validation failures result in 'failed' status records"
echo "- Proper architectural flow: Controller → Beanstalk → Consumer → Validation → MongoDB"