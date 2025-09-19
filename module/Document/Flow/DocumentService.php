<?php

namespace Module\Document\Flow;

use App\Models\BlogUser;
use App\User;
use Illuminate\Http\Request;
use Module\Document\DocumentStatus;
use Module\Document\Models\Document;

class DocumentService
{
    /**
     * @param Request $request
     * 审批流程
     */
    function approval(Request $request)
    {
        $user = $request->user();

        $uuid = $request->input('uuid');
        $step = $request->input('step');

        $document = Document::query()->where('uuid',$uuid)->firstOrFail();

        if($step == 1){ //驳回
            $document->update(['step'=>1]);
            $document->logs()->create([
                'reply'=>$request->input('reply'),
                'result' => -1,
                'user_uuid'=>$user->uuid,
                'user_name'=>$user->real_name,
            ]);

            $document->next()->update(['step'=>2,'text'=>'重新申请']);

        }else if($step == 2){
            $document->update([
                'step'=>DocumentStatus::getNextStep($step),
                'status'=>DocumentStatus::SEND,
                'status_title'=>DocumentStatus::getStatusTitle(DocumentStatus::SEND),
            ]);

            $document->logs()->create([
                'reply'=>$request->input('reply',''),
                'result' => 1,
                'user_uuid'=>$user->uuid,
                'user_name'=>$user->real_name,
                'status'=> DocumentStatus::SEND,
                'status_title'=> DocumentStatus::getStatusTitle(DocumentStatus::SEND),
                'step'=>$step,
            ]);

            $document->taskLogs()->forceDelete();//清除关联的taskLog
            $userList = ['8bcd7f26-f2d8-4e03-b4fa-0054379ecc29','d800951e-4f55-46cc-bc3d-c127c54a7e78'];//0002002-0002003
            $users = BlogUser::query()->whereIn('uuid',$userList)->get();
            foreach($users as $blogUser){
                $document->taskLogs()->create([
                    'user_uuid'=>$blogUser->uuid,
                    'user_name'=>$blogUser->real_name,
                    'status'=>DocumentStatus::SEND,
                    'status_title'=>DocumentStatus::getStatusTitle(DocumentStatus::SEND),
                ]);
            }

            $document->next()->update(['step'=>DocumentStatus::getNextStep($step),'text'=>'收文']);

        }else if($step == 3){
            $document->update(['step'=>DocumentStatus::getNextStep($step)]);

        }

        return $document->refresh()->load(['next','logs','taskLogs']);
    }


}
