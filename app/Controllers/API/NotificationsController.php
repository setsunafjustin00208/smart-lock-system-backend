<?php

namespace App\Controllers\API;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class NotificationsController extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $user = $this->request->user;
        
        $limit = $this->request->getGet('limit') ?? 50;
        $offset = $this->request->getGet('offset') ?? 0;
        $unreadOnly = $this->request->getGet('unread_only') === 'true';

        $notificationModel = new \App\Models\NotificationModel();
        
        $builder = $notificationModel->where('user_id', $user['user_id']);
        
        if ($unreadOnly) {
            $builder->where('is_read', false);
        }
        
        $notifications = $builder
            ->orderBy('created_at', 'DESC')
            ->limit($limit, $offset)
            ->findAll();

        // Get total count and unread count
        $totalCount = $notificationModel->where('user_id', $user['user_id'])->countAllResults();
        $unreadCount = $notificationModel->where('user_id', $user['user_id'])->where('is_read', false)->countAllResults();

        return $this->respond([
            'status' => 'success',
            'data' => [
                'notifications' => $notifications,
                'total_count' => $totalCount,
                'unread_count' => $unreadCount
            ]
        ]);
    }

    public function markAsRead($id)
    {
        $user = $this->request->user;
        
        $notificationModel = new \App\Models\NotificationModel();
        $notification = $notificationModel->where('id', $id)->where('user_id', $user['user_id'])->first();
        
        if (!$notification) {
            return $this->failNotFound('Notification not found');
        }

        if ($notificationModel->update($id, ['is_read' => true])) {
            return $this->respond([
                'status' => 'success',
                'message' => 'Notification marked as read'
            ]);
        }

        return $this->failServerError('Failed to mark notification as read');
    }

    public function markAllAsRead()
    {
        $user = $this->request->user;
        
        $notificationModel = new \App\Models\NotificationModel();
        
        if ($notificationModel->where('user_id', $user['user_id'])->set(['is_read' => true])->update()) {
            return $this->respond([
                'status' => 'success',
                'message' => 'All notifications marked as read'
            ]);
        }

        return $this->failServerError('Failed to mark all notifications as read');
    }

    public function delete($id)
    {
        $user = $this->request->user;
        
        $notificationModel = new \App\Models\NotificationModel();
        $notification = $notificationModel->where('id', $id)->where('user_id', $user['user_id'])->first();
        
        if (!$notification) {
            return $this->failNotFound('Notification not found');
        }

        if ($notificationModel->delete($id)) {
            return $this->respond([
                'status' => 'success',
                'message' => 'Notification deleted'
            ]);
        }

        return $this->failServerError('Failed to delete notification');
    }
}
