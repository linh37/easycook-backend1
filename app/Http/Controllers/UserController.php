<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class UserController extends Controller
{

    public function information()
    {
        $user = Auth::user();

        return response()->json([
            'status' => true,
            'user' => $user,
        ]);
    }


    public function avatar(Request $request, User $user)
    {
        $link = 'http://localhost:8000/';
        if($request->has('image'))
        {
            $image = $request->file('image')->store('images');
            Storage::delete(substr($user->image, strlen($link)));
            $user->image = $link . $image;
        }
        $user->save();

        return response()->json([
            'message' => 'Update success',
            'status' => true,
            $user->image,
        ]);
    }


    public function ingredient(Request $request, User $user)
    {
        $user->ingredients()->attach($request->ingredient, [
            'post_id' => $request->post,
        ]);

        return response()->json([
            'message' => 'Attach success',
            'status' => true,
        ]);
    }

    public function post(Request $request, User $user)
    {
        $user->posts()->toggle($request->post);

        return response()->json([
            'message' => 'Success',
            'status' => true,
        ]);
    }

    public function get_post(User $user)
    {
        $post = DB::table('post_user')
                ->select('post_id')
                ->where('user_id', $user->id)
                ->get();

        return response()->json([
            'message' => 'Success',
            'status' => true,
            'post' => $post
        ]);
    }



    public function get_ingredient(User $user)
    {

        $listStore =  DB::table('post_user')
                ->select('posts.name', 'posts.slug', 'posts.image', 'posts.id')
                ->join('posts', 'posts.id', '=', 'post_user.post_id')
                ->where([['user_id', $user->id], ['posts.deleted_at', null]] )
                ->get();

  //      $ingredients = DB::table('ingredient_user')
  //              ->select('ingredients.name', DB::raw('count(*) as total, ingredients.id'))
  //              ->join('ingredients', 'ingredients.id', '=', 'ingredient_user.ingredient_id')
  //              ->where('user_id', $user->id)
  //              ->groupByRaw('ingredients.name, ingredients.id')
  //              ->get();
            $ingredients = DB::table('ingredient_user')
                ->select('ingredients.name', DB::raw('count(*) as total, ingredients.id'))
                ->join('ingredients', 'ingredients.id', '=', 'ingredient_user.ingredient_id')
                ->join('posts', 'posts.id', '=', 'ingredient_user.post_id')
                ->where([['user_id', $user->id], ['posts.deleted_at', null]])
                ->groupByRaw('ingredients.name, ingredients.id')
                ->get();

        foreach($ingredients as $key => $value)
        {
            $per_post = DB::table('ingredient_post');
            $result = 0;

            $n = $per_post //Số công thức chứa nl
                ->where([['ingredient_id', $value->id]])
                ->count();

            $gK_i = $per_post // Số lượng nl k
                ->select('quantity')
                ->where([['ingredient_id', $value->id]])
                ->get();

            $gK = $per_post // Số lượng tb nl k
                ->where([['ingredient_id', $value->id]])
                ->avg('quantity');

            $wK = DB::table('ingredient_user')
                ->join('ingredient_post', 'ingredient_user.post_id', '=', 'ingredient_post.post_id')
                ->where([
                    ['ingredient_user.ingredient_id', $value->id],
                    ['ingredient_user.user_id', $user->id],
                    ['ingredient_post.main', 1]


                ])
                ->avg('quantity');
                $ingredients[$key]->wK = $wK;
                for($i = 0; $i < $n; $i++)
                {
                    $result += pow(((int)$gK_i[$i]->quantity - $gK), 2);
                }

                $oK = sqrt((1 / $n) * $result);

                $score = $value->total * $wK;

                $ingredients[$key]->mark = abs($oK - $score);


        }

        $high = array();

        for($i = 0; $i < count($ingredients); $i++)
        {
            $high[$ingredients[$i]->mark * 100] = $ingredients[$i]->id;
        }
        ksort($high);

        $listLiked =  DB::table('ingredient_user')
              ->select('post_id')
              ->join('posts', 'posts.id', 'ingredient_user.post_id')
              ->where([
                  ['ingredient_user.user_id', $user->id],
                  ['posts.deleted_at', null]
              ])
              ->groupBy('post_id')
              ->get();


        return response()->json([
            'message' => 'Success',
            'status' => true,
            'ingredients' => $ingredients,
            'high' => $high,
            'listStore' => $listStore,
            'listLiked' => $listLiked,
        ]);
    }

    public function remove_ingredient(Request $request, User $user)
    {
        $ingredients = DB::table('ingredient_user')
                ->where([
                    ['post_id', $request->post],
                    ['ingredient_id', $request->ingredient],
                    ['user_id', $user->id],
                ])
                ->delete();

        return response()->json([
            'message' => 'Detach success',
            'status' => true,
        ]);
    }

    public function get_love(Request $request)
    {
        $love = DB::table('ingredient_post')
            ->select('post_id', 'posts.slug', 'image', 'posts.name', 'categories.name as category')
            ->join('posts', 'posts.id', '=', 'ingredient_post.post_id')
            ->join('categories', 'posts.category_id', '=', 'categories.id')
            ->where([['ingredient_id', $request->id], ['main', 1], ['posts.deleted_at', null]])
            ->paginate(5);

        return response()->json([
            'message' => 'Detach success',
            'status' => true,
            'love' => $love,
        ]);

    }
}
