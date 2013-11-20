package nl.conceptwiki.data.scripts

class EcMeshController {

    def index() {

        // build a hashmap with Mesh ID's and there EC('s)
        def meshECs = [:]
        def ecLog = [:]
        def enzymes = [:]

        _enzymes().each { e -> enzymes[e['ID']] = e }

        def matchesForImport = []

        10.times { c ->
            def tabFile = new File("/Users/miv/Documents/workspace-nbic/enzyme-import/ECMesh${c}.csv")
            if (tabFile.exists()){
                tabFile.eachLine { line ->

                    def lineParts = line.split("\t")

                    if (lineParts.size() > 1){

                        def yn = (lineParts[1])?.trim() ?: ''
                        def EC = ((lineParts[5])?.trim())?.replaceAll(' ', '') ?: ''
                        def Mesh = (lineParts[7])?.trim() ?: ''
                        def excelPref = (lineParts[8])?.trim() ?: ''
                        def UUID = lineParts.size() >= 11 ? (lineParts[10])?.trim() ?: '' : ''

                        if ((yn == '1' || c == 0) && EC != '' && EC != 'EC'){

                            // log the ecs used and count them
                            if (!ecLog[EC]) { ecLog[EC] = 1 } else { ecLog[EC]++ }

                            def desc = ''
                            def prefTerm = excelPref
                            def altTerm = ''

                            if (enzymes[EC]){
                                desc = enzymes[EC]['CC'] ?: ''
                                prefTerm = enzymes[EC]['DE'] ?: ''
                                altTerm = enzymes[EC]['AN'] ?: ''
                            }

                            matchesForImport << ['ec': EC, 'uuid': UUID, 'pref': prefTerm, 'desc': desc ?: '', 'alt': altTerm ?: []]

                            if (Mesh){
                                if (!meshECs[Mesh]){ meshECs[Mesh] = [] }
                                meshECs[Mesh] << EC
                            }
                        }
                    }
                }
            }
        }

        // error detection
        meshECs.each { mesh, ecs ->
            if (ecs.size() > 1){
                println mesh + " has ${meshECs[mesh].size()} ecs:" + meshECs[mesh]
            }
        }

        // error detection
        ecLog.each { ecId, countEc ->
            if (countEc > 1){
                println ecId + " has ${countEc} hits "
            }
        }

        // add the non-mapped EC from the Enzymes file
        def mappedECs = ecLog.keySet()
        enzymes.each { enzymeEC, enzyme ->
            if (!(enzymeEC in mappedECs)){
                matchesForImport << ['ec': enzymeEC, 'uuid': '', 'pref': enzyme['DE'], 'desc': enzyme['CC'] ?: '', 'alt': enzyme['AN'] ?: []]
            }
        }

        matchesForImport.each { il ->

            if (il.ec != null && il.ec != 'null'){

                // change the way transferred and deleted entries are stored in the graph
                if (il?.pref?.contains('Transferred entry:') || il?.pref?.contains('Deleted entry')){
                    il.alt << il.pref
                    il.pref = "EC ${il.ec}"
                } else {
                    il.alt << "EC ${il.ec}" // add the ec as alt label
                }

                //def exportLine = "EC ${il.ec}\t${il.uuid}\t${il.pref}\t${il.desc}\t${il.alt.join("\t")}\n"
                def exportLine = "EC ${il.ec}\t${il.uuid}\t${il.pref}\t${il.desc}\thttp://enzyme.expasy.org/EC/${il.ec}\t${il.alt.join("\t")}\n"

                render(exportLine)
            }
        }
    }

    def _enzymes(){

        def listOfPrefixes = ['DE', 'AN', 'CC', 'CA', 'CF']

        def enzymes = []
        def enzyme = [:]

        //(new URL('ftp://ftp.expasy.org/databases/enzyme/enzyme.dat').text).eachLine { line ->
        new File('/Users/miv/Documents/workspace-nbic/enzyme-import/enzyme.dat').eachLine { line ->

            if (line.length() >= 1){

                if (line[0..1] == 'ID'){

                    // make AN into a list
                    if (enzyme['AN']) { enzyme['AN'] = enzyme['AN'].tokenize('.') }

                    // merge CA with CF and CC
                    enzyme['CC'] = (enzyme['CA'] ? ("Reaction(s) catalyzed: ${enzyme['CA']} ") : '') + (enzyme['CF'] ? ("Cofactor(s): ${enzyme['CF']} ") : '') + (enzyme['CC'] ? "Comment(s): ${enzyme['CC']}" : '')

                    // add enzyme to list of Enzymes
                    if (enzyme != [:]) { enzymes << enzyme }

                    // clean enzyme hashmap
                    enzyme = [:]

                    // add id
                    enzyme[line[0..1]] = _cleanLine(line)

                } else {

                    if (enzyme['ID'] && line[0..1] in listOfPrefixes){
                        if (!enzyme[line[0..1]]) { enzyme[line[0..1]] = "" } else { enzyme[line[0..1]] += " " }
                        enzyme[line[0..1]] += "${_cleanLine(line)}"
                    }
                }
            }
        }

        return enzymes

    }

    def _cleanLine(String line){

        if (line[0..1] == 'ID' || line[0..1] == 'AN' || line[0..1] == 'CA' || line[0..1] == 'CF'){
            line = line[4..-1]
        }

        if (line[0..1] == 'CC'){
            line = line[8..-1]
        }

        if (line[0..1] == 'DE'){
            if (line[(line.length()-1)..-1] == '.'){
                line = line[4..-2]
            } else {
                line = line[4..-1]
            }
        }

        // trimmmm!
        return line?.trim()
    }
}
