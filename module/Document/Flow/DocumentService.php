<?php

namespace Module\Document\Flow;

use Illuminate\Http\Request;
use Module\Document\Models\Document;

class DocumentService
{
    function approval(Request $request)
    {
        $user = $request->user();

        $uuid = $request->input('uuid');
        $step = $request->input('step');

        $document = Document::where('uuid',$uuid)->firstOrFail();

        if($step == 1){
            $document->update(['step'=>1]);
            $document->logs()->create([
                'reply'=>$request->input('reply'),
                'result' => -1,
                'user_uuid'=>$user->uuid,
                'user_name'=>$user->real_name,
            ]);
        }else if($step == 2){
            $document->update(['step'=>2]);
//            print($document->status);

            $document->logs()->create([
                'reply'=>$request->input('reply',''),
                'result' => 1,
                'user_uuid'=>$user->uuid,
                'user_name'=>$user->real_name,
                'status'=>'send',
                'status_title'=>'已申请',
                'step'=>$step,
            ]);

            $document->taskLogs()->create([
                'user_uuid'=>$user->uuid,
                'user_name'=>$user->real_name,
                'status'=>'send',
                'status_title'=>'已申请'
            ]);

            $document->next()->update(['step'=>3,'text'=>'收文']);
        }

        return $document->refresh()->load(['next','logs','taskLogs']);
    }


}
