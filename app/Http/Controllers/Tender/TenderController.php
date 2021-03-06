<?php

namespace App\Http\Controllers\Tender;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommentRequest;
use App\Http\Requests\TenderRequest;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Orchid\Platform\Core\Models\Attachment;
use Orchid\Platform\Core\Models\Attachmentable;
use Orchid\Platform\Core\Models\Comment;
use Orchid\Platform\Core\Models\Post;

class TenderController extends Controller
{
    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function index(Request $request)
    {
        $elements = Post::where('type', 'tender')->with(['tags', 'author'])->orderBy('created_at', 'DESC');


        if ($request->get('my')) {
            $elements->where('user_id',Auth::id());
        }

        if ($request->get('tags')) {
            $elements->whereTag($request->get('tags'));
        }

        if ($request->get('city')) {
            $elements->where('content->ru->city->id', $request->get('city'));
        }

        $elements = $elements->paginate(10);

        return response()->json($elements);
    }


    /**
     * @param TenderRequest $request
     *
     * @return mixed
     */
    public function store(TenderRequest $request)
    {
        $post = Post::create([
            'user_id' => Auth::id(),
            'type'    => 'tender',
            'status'  => 'publish',
            'content' => [
                'ru' => [
                    'title'       => $request->get('title'),
                    'description' => $request->get('description'),
                    'price'       => $request->get('price'),
                    'name'        => $request->get('name'),
                    'email'       => $request->get('email'),
                    'phone'       => $request->get('phone'),
                    'city'        => $request->get('city'),
                ],
            ],
            'options' => [
                'locale' => [
                    'ru' => true,
                    'en' => false,
                ],
            ],
            'slug'    => SlugService::createSlug(Post::class, 'slug', $request->get('title')),
        ]);

        if ($request->has('tags')) {
            $tags = [];
            foreach ($request->get('tags') as $item) {
                array_push($tags, $item['slug']);
            }
            $post->setTags($tags);
        }

        if ($request->has('files')) {
            $files = $request->input('files');
            foreach ($files as $file) {
                if (!is_null($file) || !empty($file)) {

                    $attach = new Attachmentable();
                    $attach->attachmentable_type = Post::class;
                    $attach->attachmentable_id = $post->id;
                    $attach->attachment_id = $file['id'];
                    $attach->save();
                }
            }
        }

        $post->save;

        return $post;

    }

    /**
     * @param Post $tender
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Post $tender)
    {
        return response()->json($tender);
    }

    /**
     * @param                $id
     * @param CommentRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function comment($id, CommentRequest $request)
    {
        Comment::create([
            'post_id'   => $id,
            'user_id'   => Auth::user()->id,
            'parent_id' => 0,
            'content'   => $request->get('content'),
            'approved'  => 1,
        ]);

        return response(200);
    }

    public function destroy(Post $tender)
    {
        if(Auth::user()->id == $tender->user_id){
            $tender->delete();
            return [
                'title'   => 'Успешно',
                'message' => 'Вы успешно удалили тендер',
                'type'    => 'success',
            ];

        }
        return response(403);

    }

}
