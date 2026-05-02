<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Marvel\Database\Models\Conversation;
use Marvel\Database\Models\Message;

class ChatController extends Controller
{
    /**
     * Get all conversations for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $conversations = Conversation::where('user_id', $user->id)
            ->orWhereHas('users', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['latest_message.user', 'user', 'shop'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'conversations' => $conversations
        ]);
    }

    /**
     * Get messages for a specific conversation.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $conversation = Conversation::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('users', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
            })
            ->firstOrFail();

        $messages = Message::where('conversation_id', $id)
            ->with(['user', 'attachments'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'conversation' => $conversation,
            'messages' => $messages
        ]);
    }

    /**
     * Send a new message.
     */
    public function storeMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'nullable|exists:conversations,id',
            'recipient_id' => 'nullable|exists:users,id',
            'body' => 'required_without:attachments|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Find or create conversation
        $conversation = null;
        if ($request->conversation_id) {
            $conversation = Conversation::findOrFail($request->conversation_id);
        } elseif ($request->recipient_id) {
            // Try to find existing conversation
            $conversation = Conversation::where('type', 'private')
                ->where(function ($query) use ($user, $request) {
                    $query->where('user_id', $user->id)
                        ->orWhere('user_id', $request->recipient_id);
                })
                ->whereHas('users', function ($q) use ($user, $request) {
                    $q->whereIn('user_id', [$user->id, $request->recipient_id]);
                })
                ->first();

            // Create new conversation if not found
            if (!$conversation) {
                $conversation = Conversation::create([
                    'user_id' => $user->id,
                    'type' => 'private',
                    'title' => null,
                ]);

                // Attach users to conversation
                $conversation->users()->attach([$user->id, $request->recipient_id]);
            }
        } else {
            return response()->json([
                'message' => 'Either conversation_id or recipient_id is required'
            ], 422);
        }

        // Create message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => $request->body ?? '',
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('chat/attachments', 'public');
                $fileType = $this->getFileType($file->getMimeType());

                Attachment::create([
                    'message_id' => $message->id,
                    'file_path' => $path,
                    'file_type' => $fileType,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        // Load relationships
        $message->load(['user', 'attachments']);

        // Broadcast message event
        event(new \App\Events\MessageSent($message));

        // Update conversation timestamp
        $conversation->touch();

        return response()->json([
            'message' => $message,
            'conversation' => $conversation
        ], 201);
    }

    /**
     * Upload attachment separately.
     */
    public function uploadAttachment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $path = $file->store('chat/attachments', 'public');
        $fileType = $this->getFileType($file->getMimeType());

        return response()->json([
            'path' => $path,
            'file_type' => $fileType,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'url' => Storage::url($path),
        ], 201);
    }

    /**
     * Mark message as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();

        $conversation = Conversation::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('users', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
            })
            ->firstOrFail();

        Message::where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Messages marked as read'
        ]);
    }

    /**
     * Get file type from MIME type.
     */
    private function getFileType($mimeType)
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } else {
            return 'file';
        }
    }
}




