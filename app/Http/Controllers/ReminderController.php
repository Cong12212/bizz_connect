<?php
namespace App\Http\Controllers;

use App\Models\Reminder;
use Illuminate\Http\Request;

class ReminderController extends Controller {
  public function index(Request $r) {
    $q = Reminder::query()->where('owner_user_id',$r->user()->id);
    if ($r->filled('status')) $q->where('status',$r->query('status'));
    if ($r->filled('before')) $q->where('due_at','<=',$r->query('before'));
    if ($r->filled('after'))  $q->where('due_at','>=',$r->query('after'));
    return $q->orderBy('due_at')->paginate(100);
  }

  public function store(Request $r) {
    $data = $r->validate([
      'contact_id'=>'required|integer|exists:contacts,id',
      'title'=>'required|string|max:255',
      'note'=>'nullable|string',
      'due_at'=>'required|date'
    ]);
    $data['owner_user_id'] = $r->user()->id;
    return response()->json(Reminder::create($data), 201);
  }

  public function update(Request $r, Reminder $reminder) {
    abort_unless($reminder->owner_user_id === $r->user()->id, 403);
    $data = $r->validate([
      'title'=>'sometimes|string|max:255',
      'note'=>'nullable|string',
      'due_at'=>'sometimes|date',
      'status'=>'sometimes|in:pending,done,skipped,cancelled'
    ]);
    $reminder->update($data);
    return $reminder;
  }

  public function destroy(Request $r, Reminder $reminder) {
    abort_unless($reminder->owner_user_id === $r->user()->id, 403);
    $reminder->delete();
    return response()->noContent();
  }

  public function markDone(Request $r, Reminder $reminder) {
    abort_unless($reminder->owner_user_id === $r->user()->id, 403);
    $reminder->update(['status'=>'done']);
    return $reminder;
  }
}
