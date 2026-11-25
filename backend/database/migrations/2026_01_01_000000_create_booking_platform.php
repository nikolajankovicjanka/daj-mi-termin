<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salons', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('category')->index();
            $t->string('address');
            $t->string('city')->index();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('official_email');
            $t->string('billing_email');
            $t->string('plan')->default('basic')->index();
            $t->date('expires_at')->nullable();
            $t->date('free_until')->nullable();
            $t->decimal('monthly_price', 10, 2)->default(0);
            $t->decimal('annual_discount', 5, 2)->default(0);
            $t->string('color')->default('#215C50');
            $t->string('photo', 2048)->nullable();
            $t->string('cover', 2048)->nullable();
            $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('salon_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('role')->default('owner');
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('salon_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->json('working_hours');
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('salon_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->decimal('price', 10, 2);
            $t->unsignedSmallInteger('duration');
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('salon_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('service_id')->constrained()->restrictOnDelete();
            $t->dateTime('start_at');
            $t->dateTime('end_at');
            $t->decimal('price', 10, 2);
            $t->string('customer_name');
            $t->string('customer_email')->nullable();
            $t->string('source');
            $t->string('status')->default('confirmed');
            $t->string('review_token', 64)->unique();
            $t->timestamps();
            $t->index(['employee_id', 'start_at', 'end_at']);
            $t->index(['salon_id', 'status', 'start_at']);
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('salon_id')->constrained()->restrictOnDelete();
            $t->unsignedTinyInteger('rating');
            $t->text('comment')->nullable();
            $t->timestamps();
        });
        Schema::create('chat_threads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('salon_id')->constrained()->restrictOnDelete();
            $t->string('token', 64)->unique();
            $t->string('customer_name');
            $t->timestamps();
        });
        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chat_thread_id')->constrained()->cascadeOnDelete();
            $t->string('sender');
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'chat_messages', 'chat_threads', 'reviews', 'bookings', 'services', 'employees', 'users', 'salons'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
