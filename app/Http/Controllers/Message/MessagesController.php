<?php

namespace App\Http\Controllers\Message;

use App\Core\Models\User;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Participant;
use Cmgmyr\Messenger\Models\Thread;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Orchid\Platform\Attachments\File;
use Illuminate\Support\Facades\Storage;
use function foo\func;

class MessagesController extends Controller
{
    /**
     * Show all of the message threads to the user.
     *
     * @return mixed
     */
    public function index()
    {

        $threads = Thread::forUser(Auth::id())->with([
            'users'    => function ($query) {
                $query->select('avatar', 'name', 'agent_name');
            },
            'messages' => function ($query) {
                $query->latest()->first();
            },
        ])
            ->groupBy('threads.id')
            ->latest('updated_at')
            ->paginate();

        $threads->getCollection()->transform(function ($value) {
            $value->isUnread = $value->isUnread(Auth::id());

            return $value;
        });


        return response()->json($threads);
    }

    /**
     * Shows a message thread.
     *
     * @param $id
     *
     * @return mixed
     */
    public function show($id)
    {
        $thread = Thread::findOrFail($id);

        $thread->messages = $thread->messages()
            ->with(['user' => function($query) {
                $query->select('id', 'avatar', 'name', 'agent_name');
            }])
            ->orderBy('created_at', 'desc')
            ->paginate();

        $thread->my = Auth::user()->only(['avatar', 'name']);

        $thread->markAsRead(Auth::user()->id);


        if (!is_null(User::select('id')->where('id', $thread->participantsUserIds())->firstOrFail())) {
            return response()->json($thread);
        }

    }

    /**
     * Creates a new message thread.
     *
     * @return mixed
     */
    public function create()
    {
        $users = User::where('id', '!=', Auth::id())->get();

        return view('messenger.create', compact('users'));
    }

    /**
     * Stores a new message thread.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $thread = Thread::create([
            'subject' => $request->get('subject','new'),
        ]);

        // Message
        Message::create([
            'thread_id' => $thread->id,
            'user_id'   => Auth::user()->id,
            'body'      => $request->get('message'),
        ]);

        // Sender
        Participant::create([
            'thread_id' => $thread->id,
            'user_id'   => Auth::user()->id,
            'last_read' => new Carbon,
        ]);

        // Recipients
        if ($request->has('recipients')) {
            $thread->addParticipant($request->get('recipients'));
        }

        return response()->json($thread);
    }

    /**
     * Adds a new message to a current thread.
     *
     * @param $id
     *
     * @return mixed
     */
    public function update($id, Request $request)
    {
        try {
            $thread = Thread::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Session::flash('error_message', 'The thread with ID: ' . $id . ' was not found.');

            return redirect('messages');
        }

        $thread->activateAllParticipants();

        // Message
        Message::create(
            [
                'thread_id' => $thread->id,
                'user_id'   => Auth::id(),
                'body'      => $request->get('message'),
            ]
        );

        // Add replier as a participant
        $participant = Participant::firstOrCreate(
            [
                'thread_id' => $thread->id,
                'user_id'   => Auth::user()->id,
            ]
        );
        $participant->last_read = new Carbon;
        $participant->save();


        return $this->show($id);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload($id, Request $request){
        $file = (new File($request->file('file'),Storage::disk('public')))->load();

        try {
            $thread = Thread::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            Session::flash('error_message', 'The thread with ID: ' . $id . ' was not found.');

            return redirect('messages');
        }

        $thread->activateAllParticipants();

        // Message
        $message = Message::create(
            [
                'thread_id' => $thread->id,
                'user_id'   => Auth::id(),
                'body'      => $file->original_name,
            ]
        );
        $message->type = $file->url();
        $message->save();

        // Add replier as a participant
        $participant = Participant::firstOrCreate(
            [
                'thread_id' => $thread->id,
                'user_id'   => Auth::user()->id,
            ]
        );
        $participant->last_read = new Carbon;
        $participant->save();


        return $this->show($id);
    }

}
