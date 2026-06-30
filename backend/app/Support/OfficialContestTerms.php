<?php

namespace App\Support;

class OfficialContestTerms
{
    public static function text(): string
    {
        return <<<'TERMS'
TERMINOS Y CONDICIONES: POLLA MUNDIALISTA SUPER CARNES 2026

1. GENERALIDADES DEL CONCURSO
La promocion comercial denominada "PRONOSTICA EL MUNDIAL Y GANA" es organizada por Super Carnes y se desarrolla en dos fases independientes dentro de la plataforma oficial.
La primera fase corresponde a la Fase de Grupos y ya se encuentra cerrada para efectos de nuevos pronosticos.
La segunda fase corresponde a las Fases Finales, desde dieciseisavos de final hasta la final.
Super Carnes podra modificar fechas operativas por razones tecnicas, regulatorias o de fuerza mayor, previa comunicacion al publico participante.

2. ELEGIBILIDAD
Podran participar personas naturales mayores de 18 anos, residentes en la Republica de Panama, portadoras de cedula de identidad personal o pasaporte vigente, que completen correctamente el proceso de registro.
No podran participar colaboradores directos de Super Carnes, personas vinculadas directa o indirectamente con la organizacion, administracion o auditoria de la promocion, ni sus familiares dentro del cuarto grado de consanguinidad y segundo de afinidad.

3. MECANICA DE PARTICIPACION
La primera fase y la segunda fase son competencias separadas. Los puntos obtenidos en la Fase de Grupos no se acumulan ni se trasladan a las Fases Finales.
Para la segunda fase, los participantes podran registrar pronosticos para los partidos habilitados desde dieciseisavos de final, octavos de final, cuartos de final, semifinal y final.
Cada pronostico de la segunda fase cerrara 15 minutos antes del inicio oficial del partido correspondiente, tomando como referencia la hora de Panama.
Los partidos se habilitaran en la plataforma cuando el proveedor oficial de datos entregue cruces con equipos definidos. Los partidos con equipos pendientes, por definir o placeholders no estaran disponibles para pronosticar.

4. PARTICIPACION DE GANADORES DE LA PRIMERA FASE
Los ganadores de la Fase de Grupos pueden participar y registrar pronosticos en la segunda fase.
Sin embargo, quienes hayan sido seleccionados como ganadores de premio en la Fase de Grupos no podran ganar premio nuevamente en la segunda fase.
Si un ganador de la Fase de Grupos aparece dentro de las primeras posiciones del ranking de Fases Finales, el sistema lo mantendra visible en el ranking, pero lo saltara para efectos de seleccion de ganadores de premio y adjudicara el cupo al siguiente participante elegible.

5. SISTEMA DE PUNTUACION
Los participantes acumularan puntos conforme a la precision de sus pronosticos en los partidos habilitados de cada fase.
En Fase de Grupos se mantiene la regla oficial ya aplicada para esa fase: 1 punto por acertar victoria del equipo favorito, 2 puntos por acertar empate, 3 puntos por acertar victoria del equipo no favorito y 3 puntos adicionales por marcador exacto.
En Fases Finales se aplicaran los puntos configurados para cada ronda en la plataforma. El ranking de la segunda fase sumara los puntos de pronosticos obtenidos desde dieciseisavos de final hasta la final y las facturas validas registradas despues del cierre operativo de la Fase de Grupos.
Para la segunda fase no se sumaran puntos, marcadores exactos, facturas, desempates ni beneficios obtenidos durante la Fase de Grupos.

6. PREMIOS
Los premios de cada fase se otorgan de manera independiente.
Los premios correspondientes a la Fase de Grupos se rigen por la seleccion y ranking de esa primera fase.
Al finalizar las Fases Finales, los 20 participantes elegibles con mayor puntuacion acumulada desde dieciseisavos de final hasta la final recibiran un bono o tarjeta de regalo para compras en Super Carnes por USD 200.00 cada uno.
Los premios no son transferibles, no son canjeables por dinero en efectivo y no podran ser sustituidos por otros bienes o servicios.

7. CRITERIOS DE DESEMPATE
En caso de empate en una fase, se aplicaran sucesivamente estos criterios:
1. Mayor cantidad de marcadores exactos acertados dentro de la fase correspondiente.
2. Mayor cantidad de facturas validas registradas dentro del periodo aplicable de la fase correspondiente, solo para fases donde la regla de facturas este habilitada.
3. Mayor monto acumulado en compras validas dentro del periodo aplicable de la fase correspondiente, solo para fases donde la regla de facturas este habilitada.
4. Mayor aproximacion al total de goles anotados en la Fase de Grupos, aplicable solo para desempates de la Fase de Grupos.
5. Fecha y hora de registro mas temprana en el sistema oficial de la plataforma.
Si despues de aplicar los criterios anteriores persiste un empate exacto en el corte de ganadores, la seleccion automatica quedara bloqueada hasta que Super Carnes resuelva el empate conforme a los mecanismos administrativos y legales aplicables.

8. VALIDACION DE FACTURAS
Toda factura registrada sera verificada contra los controles disponibles de Super Carnes y/o la Direccion General de Ingresos (DGI), segun aplique.
Solo seran validas las facturas legitimas, con CUFE verificable, no registradas previamente y que cumplan con los montos, fechas y condiciones de la fase correspondiente.
El intento de usar facturas falsas, alteradas, duplicadas o pertenecientes a terceros constituye causal inmediata de descalificacion.

9. NOTIFICACION Y ENTREGA DE PREMIOS
Los ganadores oficiales de cada fase seran anunciados dentro de los cinco dias calendario siguientes al cierre de la fase correspondiente, a traves de los canales oficiales de Super Carnes.
Ademas, seran contactados via telefonica y/o correo electronico. Si un ganador potencial no responde dentro de los cinco dias calendario siguientes al primer intento de contacto, perdera el derecho al premio y Super Carnes podra adjudicarlo al siguiente participante elegible con mayor puntuacion.

10. DESCALIFICACION
Super Carnes podra descalificar inmediatamente a cualquier participante que incumpla estos terminos y condiciones, proporcione informacion falsa o incompleta, intente manipular la plataforma o el sistema de puntuacion, registre facturas fraudulentas o pertenecientes a terceros, o realice actos que afecten la transparencia o integridad de la promocion.

11. PROTECCION DE DATOS PERSONALES
Los datos personales suministrados seran utilizados exclusivamente para la administracion, desarrollo y ejecucion de la promocion, asi como para la validacion de identidad y entrega de premios, de conformidad con la Ley 81 de 2019 y demas normas aplicables de la Republica de Panama.

12. ACEPTACION
La participacion en la promocion implica el conocimiento, aceptacion plena e incondicional de los presentes terminos y condiciones.
TERMS;
    }
}
