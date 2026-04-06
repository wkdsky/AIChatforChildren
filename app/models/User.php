<?php

namespace App\Models;

use Core\BaseModel;
use PDO;


class User extends BaseModel
{

    protected $table = 'users';

    public function __construct()
    {
        parent::__construct();
    }

    public function emailExists($email)
    {
        $query = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function childNameExists($name, $excludeUserId = null)
    {
        $query = "SELECT id FROM users WHERE role = 'child' AND name = :name";
        $params = ['name' => $name];

        if ($excludeUserId !== null) {
            $query .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeUserId;
        }

        $query .= " LIMIT 1";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function createUser($name, $email, $password, $role, $verificationCode)
    {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (name, email, password, role, verification_code) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$name, $email, $password, $role, $verificationCode]);
    }

    public function createChildUserForParent($parentId, array $childData)
    {
        $sql = "INSERT INTO {$this->table} (
            name,
            email,
            password,
            role,
            verification_code,
            verification_status,
            parent_id,
            gender,
            birth_date,
            allowed_login_start,
            allowed_login_end,
            daily_login_minutes
        ) VALUES (
            :name,
            :email,
            :password,
            'child',
            NULL,
            'verified',
            :parent_id,
            :gender,
            :birth_date,
            :allowed_login_start,
            :allowed_login_end,
            :daily_login_minutes
        )";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'name' => $childData['name'],
            'email' => $childData['email'],
            'password' => $childData['password'],
            'parent_id' => $parentId,
            'gender' => $childData['gender'],
            'birth_date' => $childData['birth_date'],
            'allowed_login_start' => $childData['allowed_login_start'] ?? '00:00:00',
            'allowed_login_end' => $childData['allowed_login_end'] ?? '23:50:00',
            'daily_login_minutes' => $childData['daily_login_minutes'] ?? 120,
        ]);
    }

    public function findByEmail($email)
    {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt =$this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_OBJ);
        return $user ? $user : null;
    }

    public function findChildByName($name)
    {
        $sql = "SELECT * FROM users WHERE role = 'child' AND name = :name LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_OBJ);
        return $user ? $user : null;
    }

    public function getChildrenByParentId($parentId)
    {
        $sql = "SELECT id, name, gender, birth_date, created_at
                FROM {$this->table}
                WHERE role = 'child' AND parent_id = :parent_id
                ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['parent_id' => $parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUsers()
    {
        return $this->getAll();
    }

    public function getUserById($id)
    {
        return $this->getById($id);
    }

    public function updateUser($id, $data)
    {
        $setClause = [];
        $values = [];
    
        foreach ($data as $key => $value) {
            $setClause[] = "$key = ?";
            $values[] = $value;
        }
    
        $setClause = implode(", ", $setClause);
        $sql = "UPDATE {$this->table} SET $setClause WHERE id = ?";
    
        $values[] = $id;
    
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    

    public function deleteUser($id)
    {
        return $this->delete($id);
    }
}
