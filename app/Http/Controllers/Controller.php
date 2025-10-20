<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         version="1.0.0",
 *         title="Bizz Connect API Documentation",
 *         description="RESTful API for Bizz Connect - Contact & Business Management System",
 *         @OA\Contact(
 *             email="support@bizzconnect.com"
 *         )
 *     ),
 *     @OA\Server(
 *         url=L5_SWAGGER_CONST_HOST,
 *         description="API Server"
 *     )
 * )
 * 
 * @OA\Components(
 *     @OA\SecurityScheme(
 *         securityScheme="bearerAuth",
 *         type="http",
 *         scheme="bearer",
 *         bearerFormat="Sanctum",
 *         description="Enter your Bearer token in the format: Bearer {token}"
 *     ),
 *     @OA\Schema(
 *         schema="Contact",
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="company", type="string"),
 *         @OA\Property(property="job_title", type="string"),
 *         @OA\Property(property="email", type="string"),
 *         @OA\Property(property="phone", type="string"),
 *         @OA\Property(property="address", type="string"),
 *         @OA\Property(property="notes", type="string"),
 *         @OA\Property(property="linkedin_url", type="string"),
 *         @OA\Property(property="website_url", type="string"),
 *         @OA\Property(property="owner_user_id", type="integer"),
 *         @OA\Property(property="tags", type="array", @OA\Items(ref="#/components/schemas/Tag")),
 *         @OA\Property(property="created_at", type="string", format="date-time"),
 *         @OA\Property(property="updated_at", type="string", format="date-time")
 *     ),
 *     @OA\Schema(
 *         schema="Tag",
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="owner_user_id", type="integer"),
 *         @OA\Property(property="created_at", type="string", format="date-time")
 *     ),
 *     @OA\Schema(
 *         schema="User",
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="email", type="string"),
 *         @OA\Property(property="email_verified_at", type="string", format="date-time"),
 *         @OA\Property(property="created_at", type="string", format="date-time"),
 *         @OA\Property(property="updated_at", type="string", format="date-time")
 *     ),
 *     @OA\Schema(
 *         schema="Reminder",
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="title", type="string"),
 *         @OA\Property(property="note", type="string"),
 *         @OA\Property(property="due_at", type="string", format="date-time"),
 *         @OA\Property(property="status", type="string", enum={"pending", "done", "skipped", "cancelled"}),
 *         @OA\Property(property="channel", type="string", enum={"app", "email", "calendar"}),
 *         @OA\Property(property="contact_id", type="integer"),
 *         @OA\Property(property="owner_user_id", type="integer"),
 *         @OA\Property(property="contacts", type="array", @OA\Items(ref="#/components/schemas/Contact")),
 *         @OA\Property(property="created_at", type="string", format="date-time"),
 *         @OA\Property(property="updated_at", type="string", format="date-time")
 *     ),
 *     @OA\Schema(
 *         schema="UserNotification",
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="type", type="string"),
 *         @OA\Property(property="title", type="string"),
 *         @OA\Property(property="body", type="string"),
 *         @OA\Property(property="status", type="string", enum={"unread", "read", "done"}),
 *         @OA\Property(property="scheduled_at", type="string", format="date-time"),
 *         @OA\Property(property="read_at", type="string", format="date-time"),
 *         @OA\Property(property="owner_user_id", type="integer"),
 *         @OA\Property(property="created_at", type="string", format="date-time")
 *     )
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
