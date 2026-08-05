# **Laravel Backend Implementation Plan for AI Agent**

**Project:** Islamic Educational Content Platform  
**Target Persona:** Autonomous AI Coding Agent  
**Context:** This document serves as a strict, sequential blueprint to implement the **Laravel 13** backend using **PHP 8.5** based on the predefined Software Requirements Specification (SRS). Proceed sequentially from Phase 1 to Phase 8\.

## **Phase 1: Project Initialization & Configuration**

> * **Step 1.1:** Initialize a new Laravel 13 project. Ensure the environment is strictly running **PHP 8.5**. (Command: composer create-project laravel/laravel backend)  
> * **Step 1.2:** Configure the .env file. Set up database credentials (assume MySQL or PostgreSQL based on final decision) and add the following custom variables:  
>   FASTAPI\_INTERNAL\_URL=http://fastapi-service:8000  
>   FRONTEND\_URL=http://localhost:3000  
>             
> * **Step 1.3:** Install and configure Laravel Sanctum for API token authentication. (Command: php artisan install:api or equivalent Sanctum setup).  
> * **Step 1.4:** Configure CORS in config/cors.php to allow requests strictly from the FRONTEND\_URL with supports\_credentials \=\> true.

## **Phase 2: Database Migrations**

Create the following migrations using php artisan make:migration. Execute in this exact order to respect foreign key constraints:

> 1. **Modify Users Table:** Add role (enum: 'super\_admin', 'sub\_admin') and is\_active (boolean, default: true).  
> 2. **Create Documents Table:** Columns: id, uploader\_id (foreign key to users), source\_type (enum: 'text', 'pdf'), extracted\_text (longText), timestamps.  
> 3. **Create Operations Table:** Columns: id, document\_id (foreign key), category (enum: 'aqeedah', 'fiqh', 'seerah', 'tazkiyah'), operation\_type (enum: 'summarization', 'question\_generation'), status (string, default: 'pending'), timestamps.  
> 4. **Create AI Outputs Table:** Columns: id, operation\_id (foreign key), raw\_json (json), timestamps.  
> 5. **Create Approved Versions Table:** Columns: id, ai\_output\_id (foreign key), edited\_content (json), approver\_id (foreign key to users), timestamps.  
> 6. **Create Quizzes Table:** Columns: id, approved\_version\_id (foreign key), unique\_token (string, unique), is\_active (boolean, default: true), timestamps.  
> 7. **Create Quiz Submissions Table:** Columns: id, quiz\_id (foreign key), name (string), email (string), score (integer), timestamps.  
> 8. **Create Quiz Answers Table:** Columns: id, submission\_id (foreign key), question\_text (text), participant\_answer (text), correct\_answer (text), is\_correct (boolean), timestamps.  
> 9. **Create Global Settings Table:** Columns: id, setting\_key (string, unique), setting\_value (string), timestamps.

## **Phase 3: Eloquent Models & Relationships**

Generate models using php artisan make:model \[Name\]. Implement the following relationships:

> * User: hasMany(Document::class, 'uploader\_id')  
> * Document: belongsTo(User::class, 'uploader\_id'), hasMany(Operation::class)  
> * Operation: belongsTo(Document::class), hasOne(AiOutput::class)  
> * AiOutput: belongsTo(Operation::class), hasMany(ApprovedVersion::class)  
> * ApprovedVersion: belongsTo(AiOutput::class), hasOne(Quiz::class)  
> * Quiz: belongsTo(ApprovedVersion::class), hasMany(QuizSubmission::class)  
> * QuizSubmission: belongsTo(Quiz::class), hasMany(QuizAnswer::class)

*Note: Ensure all models explicitly define their $fillable arrays to prevent mass assignment vulnerabilities.*

## **Phase 4: API Routing & Middleware**

Define the routes in routes/api.php. Group all routes under a /v1/ prefix.

Route::prefix('v1')-\>group(function () {  
    // Public routes (Authentication, Public Quiz Access)  
    Route::post('/login', \[AuthController::class, 'login'\]);  
    Route::get('/quizzes/{token}', \[PublicQuizController::class, 'show'\]);  
    Route::post('/quizzes/{token}/submit', \[PublicQuizController::class, 'submit'\]);

    // Protected Admin routes  
    Route::middleware('auth:sanctum')-\>group(function () {  
        Route::post('/logout', \[AuthController::class, 'logout'\]);  
        Route::apiResource('documents', DocumentController::class);  
        Route::post('/operations', \[OperationController::class, 'store'\]);  
        Route::post('/approved-versions', \[ApprovedVersionController::class, 'store'\]);  
        Route::post('/quizzes', \[QuizManagementController::class, 'store'\]);  
        Route::get('/quizzes/{quiz}/results', \[QuizManagementController::class, 'results'\]);  
          
        // Super Admin only routes  
        Route::middleware('role:super\_admin')-\>group(function () {  
            Route::apiResource('sub-admins', SubAdminController::class);  
            Route::put('/settings', \[GlobalSettingsController::class, 'update'\]);  
        });  
    });  
});

## **Phase 5: Core Services & Controllers Logic**

Implement the controllers handling the core business logic. Strictly follow the Single Responsibility Principle.

> * **DocumentController:** Handle upload. Validate PDF (mimes:pdf|max:20480). Store file temporarily, extract text (or simulate text receipt if frontend extracts it), and save to documents.  
> * **OperationController (The AI Integration):**  
  1. Validate request (document\_id, category, operation\_type).  
  2. Check global\_settings to ensure monthly AI operations limit is not exceeded.  
  3. Create pending Operation record.  
  4. Make synchronous HTTP POST to env('FASTAPI\_INTERNAL\_URL') . '/process' with timeout of 60s using Laravel's Http facade.  
  5. Save raw response to ai\_outputs.  
  6. Return the AI Output to the frontend.  
> * **ApprovedVersionController:** Receive user-edited JSON. Validate it. Create a NEW ApprovedVersion record. NEVER overwrite the AiOutput. Wrap this in a DB::transaction().  
> * **PublicQuizController:** Verify unique token. Check if quiz is\_active. Check if participant email already exists for this quiz (prevent duplicates). Calculate score dynamically upon submission by comparing participant\_answer with correct\_answer. Save to quiz\_submissions and quiz\_answers within a database transaction.

## **Phase 6: Validation & Form Requests**

Generate FormRequest classes for every POST/PUT endpoint (e.g., php artisan make:request StoreDocumentRequest).

> * Enforce rigid type checking and maximum lengths.  
> * Sanitize all text inputs using Laravel's built-in strip\_tags() where HTML is not explicitly required (summaries may allow safe HTML tags).

## **Phase 7: Error Handling & Responses**

> * Implement standard JSON response formatting using a base API controller or Trait (e.g., { "success": true, "data": {...}, "message": "..." }).  
> * Catch HTTP Client exceptions (FastAPI timeout/failure) and return a structured 500 error allowing the frontend to display an Arabic retry message. Ensure no system data is corrupted if the FastAPI call fails.