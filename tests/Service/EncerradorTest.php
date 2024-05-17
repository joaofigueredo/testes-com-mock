<?php

namespace Service;

use Alura\Leilao\Dao\Leilao as LeilaoDao;
use Alura\Leilao\Model\Leilao;
use Alura\Leilao\Service\Encerrador;
use Alura\Leilao\Service\EnviadorEmail;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EncerradorTest extends TestCase
{
    private $encerrador;
    private $leilaoFiat147;
    private $leilaoVariant;
    /** @var MockObject */
    private $enviadorEmail;
    protected function setUp(): void
    {
        $this->leilaoFiat147 = new Leilao(
            'fiat 147 0km',
            new \DateTimeImmutable('8 days ago')
        );
        $this->leilaoVariant = new Leilao(
            'Variant 1972 0km',
            new \DateTimeImmutable('10 days ago')
        );

        $leilaoDao = $this->createMock(LeilaoDao::class);
        $leilaoDao->method('recuperarNaoFinalizados')
            ->willReturn([$this->leilaoFiat147, $this->leilaoVariant]);
        $leilaoDao->method('recuperarFinalizados')
            ->willReturn([$this->leilaoFiat147, $this->leilaoVariant]);
        $leilaoDao->expects($this->exactly(2))
            ->method('atualiza')
            ->withConsecutive(
                [$this->leilaoFiat147],
                [$this->leilaoVariant]);

        $this->enviadorEmail = $this->createMock(EnviadorEmail::class);

        $this->encerrador = new Encerrador($leilaoDao, $this->enviadorEmail);
    }

    public function testLeiloesComMaisDeUmaSemanaDevemSerEncerrados()
    {

        $this->encerrador->encerra();

        $leiloes = [$this->leilaoFiat147, $this->leilaoVariant];
        self::assertCount(2, $leiloes);
        self::assertTrue($leiloes[0]->estaFinalizado());
        self::assertTrue($leiloes[1]->estaFinalizado());
    }

    public function testDeveContinuarOProcessamentoAoEncontrarErroAoEnviarEmail()
    {
        $e = new \DomainException('Erro ao enviar email!');
        $this->enviadorEmail->expects($this->exactly(2))
            ->method('notificarTerminoDoLeilao')
            ->willThrowException($e);

        $this->encerrador->encerra();
    }

    public function testSoDeveEnviarLeilaoPorEmailAposFinalizado()
    {
        $this->enviadorEmail->expects($this->exactly(2))
            ->method('notificarTerminoDoLeilao')
            ->willReturnCallback(function(Leilao $leilao){
                static::assertTrue($leilao->estaFinalizado());
            });

        $this->encerrador->encerra();
    }
}