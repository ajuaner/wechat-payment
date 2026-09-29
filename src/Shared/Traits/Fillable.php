<?php
namespace Qinii\WechatPayment\Shared\Traits;

trait Fillable
{
    /**
     * 使用数组创建并填充 DTO。
     *
     * @param array<string, mixed> $data
     * @return static 已填充的 DTO 实例
     */
    public static function fromArray(array $data): static
    {
        return (new static())->fill($data);
    }

    /**
     * 将数组中的已知字段写入当前 DTO，未知字段会被忽略。
     *
     * @param array<string, mixed> $data
     * @return static 当前 DTO 实例
     */
    public function fill(array $data): static
    {
        foreach ($data as $key => $value) {
            if (! property_exists($this, $key)) {
                continue;
            }

            $this->{$key} = $this->normalizeValue($key, $value);
        }

        return $this;
    }

    /** 对传入字段进行类型或格式标准化，子类可按业务覆盖。 */
    protected function normalizeValue(string $key, mixed $value): mixed
    {
        return $value;
    }

    /** 将路径模板中的占位符替换为实际参数。 */
    protected function uri(string $template, array $params = []): string
    {
        foreach ($params as $key => $value) {
            $template = str_replace(
                '{' . $key . '}',
                (string) $value,
                $template
            );
        }

        return $template;
    }
}
