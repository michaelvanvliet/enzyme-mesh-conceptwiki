package nl.conceptwiki.data.scripts

class EnzymeController {

    def index() { 
        
        def listOfPrefixes = ['DE', 'AN', 'CC']
    
        def enzymes = []
        def enzyme = [:]
        
        //(new URL('ftp://ftp.expasy.org/databases/enzyme/enzyme.dat').text).eachLine { line ->
        new File('C:\\Users\\Michael\\Downloads\\enzyme.dat').eachLine { line ->
            
            if (line.length() > 5){
            
                if (line[0..1] == 'ID'){

                    // make AN into a list
                    if (enzyme['AN']) { enzyme['AN'] = enzyme['AN'].tokenize('.') }

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
    
        
        enzymes.each { e ->
            render "${e}<br />"
        }
        
    }
    
    def _cleanLine(String line){
                        
        if (line[0..1] == 'ID' || line[0..1] == 'AN'){
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
