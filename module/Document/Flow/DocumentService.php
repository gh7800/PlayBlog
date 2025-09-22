<?php

namespace Module\Document\Flow;

use App\Models\BlogUser;
use App\Models\Next;
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
        $nextData = ['驳回','同意'];
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

        }else if($step == 2){ //发送申请
            $document->update([
                'step'=>$step,
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
                    'status'=>DocumentStatus::RECEIVE,
                    'status_title'=>DocumentStatus::getStatusTaskTitle(DocumentStatus::RECEIVE),
                ]);
            }

            $document->next()->update(['step'=>DocumentStatus::getNextStep($step),'text'=>'收文']);

        }else if($step == 3){ //行办收文
            $document->update([
                'step'=>$step,
                'status'=>DocumentStatus::PENDING,
                'status_title'=>DocumentStatus::getStatusTaskTitle(DocumentStatus::PENDING),
            ]);

            $document->logs()->create([
                'reply'=>$request->input('reply',''),
                'result' => 1,
                'user_uuid'=>$user->uuid,
                'user_name'=>$user->real_name,
                'status'=> DocumentStatus::RECEIVE,
                'status_title'=> DocumentStatus::getStatusTitle(DocumentStatus::RECEIVE),
                'step'=>$step,
            ]);

            $document->taskLogs()->forceDelete();//清除关联的taskLog

            $document->taskLogs()->create([
                'user_uuid'=>'$2y$10$Zh23RAulpGATU7y0gjyV8.5Lx/5LsFBdTi1UGAlCwrfpwdZJEZOyG',
                'user_name'=>'朱连杰',
                'status'=>DocumentStatus::PENDING,
                'status_title'=>DocumentStatus::getStatusTaskTitle(DocumentStatus::PENDING),
            ]);

            $document -> next() -> forceDelete();
            $document->next()->createMany([
                ['step' => DocumentStatus::getNextStep($step), 'text' => '同意'],
                ['step' => 1, 'text' => '驳回'],
            ]);

        }else if($step == 4){ //部长审批
            $document->update([
                'step'=>$step,
                'status'=>DocumentStatus::APPROVED,
                'status_title'=>DocumentStatus::getStatusTaskTitle(DocumentStatus::APPROVED),
            ]);

            $document->logs()->create([
                'reply'=>$request->input('reply',''),
                'result' => 1,
                'user_uuid'=>$user->uuid,
                'user_name'=>$user->real_name,
                'status'=> DocumentStatus::PENDING,
                'status_title'=> DocumentStatus::getStatusTitle(DocumentStatus::PENDING),
                'step'=>$step,
            ]);

            $document->taskLogs()->forceDelete();//清除关联的taskLog

            $document->taskLogs()->create([
                'user_uuid'=>'a502484e-5040-42e0-933e-1ccca37d4c12',
                'user_name'=>'王文智',
                'status'=>DocumentStatus::APPROVED,
                'status_title'=>DocumentStatus::getStatusTaskTitle(DocumentStatus::APPROVED),
            ]);

            $document -> next() -> forceDelete();
            $document->next()->createMany([
                ['step' => DocumentStatus::getNextStep($step), 'text' => '同意'],
                ['step' => 1, 'text' => '驳回'],
            ]);
        }else if($step == 5){ //分管领导审批
            $document->update([
                'step'=>$step,
                'status'=>DocumentStatus::COMPLETED,
                'status_title'=>DocumentStatus::getStatusTitle(DocumentStatus::COMPLETED),
            ]);

            $document->logs()->create([
                'reply'=>$request->input('reply',''),
                'result' => 1,
                'user_uuid'=>$user->uuid,
                'user_name'=>$user->real_name,
                'status'=> DocumentStatus::APPROVED,
                'status_title'=> DocumentStatus::getStatusTitle(DocumentStatus::APPROVED),
                'step'=>$step,
            ]);

            $document->taskLogs()->forceDelete();//清除关联的taskLog

            $document -> next() -> forceDelete();

        }

        return $document->refresh()->load(['logs']);
    }


}
