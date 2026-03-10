<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private array $encryptedFields = ['first_name', 'last_name', 'email', 'phone', 'dob', 'address', 'id_number'];

    public function up(): void
    {
        DB::table('customers')->orderBy('id')->each(function ($customer) {
            $updates = [];

            foreach ($this->encryptedFields as $field) {
                $value = $customer->$field;
                if ($value === null) {
                    continue;
                }

                if ($this->isEncrypted($value)) {
                    continue;
                }

                $updates[$field] = Crypt::encryptString($value);
            }

            // Compute email_hash using HMAC with app key
            if (isset($updates['email'])) {
                // Plain email still available before encrypting
                $updates['email_hash'] = hash_hmac('sha256', strtolower($customer->email), config('app.key'));
            } elseif ($customer->email && ! $customer->email_hash) {
                // Email already encrypted — decrypt to compute hash
                try {
                    $plainEmail = Crypt::decryptString($customer->email);
                    $updates['email_hash'] = hash_hmac('sha256', strtolower($plainEmail), config('app.key'));
                } catch (\Exception $e) {
                    Log::warning("Could not compute email_hash for customer {$customer->id}: {$e->getMessage()}");
                }
            }

            if (! empty($updates)) {
                DB::table('customers')->where('id', $customer->id)->update($updates);
            }
        });
    }

    public function down(): void
    {
        // Decrypt back to plain text
        DB::table('customers')->orderBy('id')->each(function ($customer) {
            $updates = [];

            foreach ($this->encryptedFields as $field) {
                $value = $customer->$field;
                if ($value === null) {
                    continue;
                }

                if ($this->isEncrypted($value)) {
                    try {
                        $updates[$field] = Crypt::decryptString($value);
                    } catch (\Exception $e) {
                        // Leave as-is if decryption fails
                    }
                }
            }

            $updates['email_hash'] = null;

            if (! empty($updates)) {
                DB::table('customers')->where('id', $customer->id)->update($updates);
            }
        });
    }

    private function isEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }
        $json = json_decode($decoded, true);
        if (! is_array($json) || ! isset($json['iv'], $json['value'], $json['mac'])) {
            return false;
        }

        // Verify by attempting decryption
        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
};
